<?php

namespace App\Services\Delivery;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Боевой: OAuth + platform_station_id.
 * Тест (тумблер): tst-хост + демо-токен + демо-станция.
 */
class YandexDeliveryService
{
    public function isConfigured(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        [$token] = $this->credentials($tenant);

        return $token !== ''
            && ($this->platformStationId($tenant) !== '' || $this->sourceAddress($tenant) !== '');
    }

    public function isPlatformConfigured(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        [$token] = $this->credentials($tenant);

        return $token !== '' && $this->platformStationId($tenant) !== '';
    }

    public function isExpressConfigured(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        [$token] = $this->credentials($tenant);

        return $token !== '' && $this->sourceAddress($tenant) !== '';
    }

    public function isTestContour(Tenant $tenant): bool
    {
        // Явный тумблер в настройках — всегда тестовый контур
        if ((bool) $tenant->setting('yandex_delivery_test_mode', false)) {
            return true;
        }

        $token = trim((string) $tenant->setting('yandex_delivery_token', ''));
        $testToken = (string) config('services.yandex_delivery.test_token');

        if ($token !== '' && $token === $testToken) {
            return true;
        }

        if ($token === '' && config('services.yandex_delivery.fallback_to_test')) {
            return true;
        }

        return false;
    }

    protected function sourceAddress(Tenant $tenant): string
    {
        $source = trim((string) $tenant->setting('yandex_delivery_source_address', ''));
        if ($source !== '') {
            return $source;
        }

        if ($this->isTestContour($tenant)) {
            return (string) config('services.yandex_delivery.test_source_address');
        }

        $city = trim((string) $tenant->setting('yandex_delivery_default_city', ''));

        return $city !== '' ? $city : '';
    }

    protected function credentials(Tenant $tenant): array
    {
        $token = trim((string) $tenant->setting('yandex_delivery_token', ''));
        $taxiClass = (string) ($tenant->setting('yandex_delivery_taxi_class') ?: 'express');
        $lon = $tenant->setting('yandex_delivery_source_lon');
        $lat = $tenant->setting('yandex_delivery_source_lat');
        $useTest = $this->isTestContour($tenant);
        $testToken = (string) config('services.yandex_delivery.test_token');

        // На тестовом контуре — только тестовый OAuth (боевой ключ на tst не работает)
        if ($useTest) {
            $token = $testToken;
        }

        $source = $this->sourceAddress($tenant);
        if ($source === '' && $useTest) {
            $source = (string) config('services.yandex_delivery.test_source_address');
            $lon = config('services.yandex_delivery.test_source_lon');
            $lat = config('services.yandex_delivery.test_source_lat');
        }

        $testBase = rtrim((string) config('services.yandex_delivery.test_base_url'), '/');
        $prodBase = rtrim((string) config('services.yandex_delivery.prod_base_url'), '/');
        $baseUrl = $useTest ? $testBase : $prodBase;

        $customBase = trim((string) $tenant->setting('yandex_delivery_base_url', ''));
        if ($customBase !== '') {
            $baseUrl = rtrim($customBase, '/');
        }

        return [
            $token,
            $source,
            $baseUrl,
            ($lon !== null && $lon !== '') ? (float) $lon : null,
            ($lat !== null && $lat !== '') ? (float) $lat : null,
            in_array($taxiClass, ['courier', 'express', 'cargo'], true) ? $taxiClass : 'express',
            $useTest,
        ];
    }

    public function platformStationId(Tenant $tenant): string
    {
        // Тестовый контур: всегда демо-станция (боевой station_id на tst не обслуживается)
        if ($this->isTestContour($tenant)) {
            $testStation = trim((string) config('services.yandex_delivery.test_station_id'));
            if ($testStation !== '') {
                return $testStation;
            }
        }

        return trim((string) $tenant->setting('yandex_delivery_station_id', ''));
    }

    protected function platformBaseUrl(Tenant $tenant): string
    {
        $custom = trim((string) $tenant->setting('yandex_delivery_platform_base_url', ''));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        return $this->isTestContour($tenant)
            ? rtrim((string) config('services.yandex_delivery.platform_test_base_url'), '/')
            : rtrim((string) config('services.yandex_delivery.platform_prod_base_url'), '/');
    }

    public function checkPrice(
        Tenant $tenant,
        string $destinationAddress,
        float $weightKg = 1.0,
        ?float $lengthM = 0.3,
        ?float $widthM = 0.2,
        ?float $heightM = 0.15,
        int $quantity = 1,
    ): ?array {
        [$token, $sourceAddress, $baseUrl, $srcLon, $srcLat, $taxiClass] = $this->credentials($tenant);

        if ($token === '' || $sourceAddress === '' || trim($destinationAddress) === '') {
            return null;
        }

        $sourcePoint = ['id' => 1, 'fullname' => $sourceAddress];
        if ($srcLon !== null && $srcLat !== null) {
            $sourcePoint['coordinates'] = [$srcLon, $srcLat];
        }

        $payload = [
            'items' => [[
                'size' => [
                    'length' => max(0.01, (float) $lengthM),
                    'width' => max(0.01, (float) $widthM),
                    'height' => max(0.01, (float) $heightM),
                ],
                'weight' => max(0.1, $weightKg),
                'quantity' => max(1, $quantity),
                'pickup_point' => 1,
                'dropoff_point' => 2,
            ]],
            'route_points' => [
                $sourcePoint,
                ['id' => 2, 'fullname' => trim($destinationAddress)],
            ],
            'requirements' => ['taxi_class' => $taxiClass],
        ];

        try {
            $response = Http::withToken($token)->acceptJson()
                ->withHeaders(['Accept-Language' => 'ru'])->timeout(20)
                ->post($baseUrl.'/b2b/cargo/integration/v2/check-price', $payload);

            if (! $response->successful()) {
                Log::warning('Yandex Express failed', [
                    'status' => $response->status(),
                    'base' => $baseUrl,
                    'body' => mb_substr($response->body(), 0, 400),
                ]);

                return null;
            }

            $json = $response->json() ?? [];
            $price = $this->extractPrice($json);
            if ($price === null) {
                return null;
            }

            $result = [
                'price' => $price,
                'currency' => 'RUB',
                'type' => 'express',
                'taxi_class' => $taxiClass,
                'label' => 'Курьер сегодня',
                'raw' => $json,
            ];
            if (isset($json['distance_meters'])) {
                $result['distance_meters'] = (float) $json['distance_meters'];
            }
            if (isset($json['eta'])) {
                $result['eta'] = (float) $json['eta'];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Yandex Express: '.$e->getMessage());

            return null;
        }
    }

    public function checkPriceRussia(
        Tenant $tenant,
        string $destinationAddress,
        float $weightKg = 1.0,
        float $assessedRub = 1000.0,
        string $tariff = 'time_interval',
    ): ?array {
        [$token, , , , , , $useTest] = $this->credentials($tenant);
        $station = $this->platformStationId($tenant);
        $baseUrl = $this->platformBaseUrl($tenant);

        if ($token === '' || $station === '' || trim($destinationAddress) === '') {
            Log::info('Yandex Platform skip', [
                'has_token' => $token !== '',
                'station' => $station !== '' ? 'set' : 'empty',
                'test' => $useTest,
            ]);

            return null;
        }

        $payload = [
            'source' => ['platform_station_id' => $station],
            'destination' => ['address' => trim($destinationAddress)],
            'tariff' => in_array($tariff, ['time_interval', 'self_pickup'], true) ? $tariff : 'time_interval',
            'total_weight' => (int) max(100, round($weightKg * 1000)),
            'total_assessed_price' => (int) max(100, round($assessedRub * 100)),
            'client_price' => 0,
            'payment_method' => 'already_paid',
        ];

        try {
            $response = Http::withToken($token)->acceptJson()
                ->withHeaders(['Accept-Language' => 'ru'])->timeout(20)
                ->post($baseUrl.'/api/b2b/platform/pricing-calculator', $payload);

            if (! $response->successful()) {
                Log::warning('Yandex Platform failed', [
                    'status' => $response->status(),
                    'base' => $baseUrl,
                    'station' => $station,
                    'tariff' => $tariff,
                    'body' => mb_substr($response->body(), 0, 400),
                ]);

                return null;
            }

            $json = $response->json() ?? [];
            $price = $this->parseMoneyString((string) ($json['pricing_total'] ?? ''));
            if ($price === null && isset($json['pricing_total']) && is_numeric($json['pricing_total'])) {
                $price = round((float) $json['pricing_total'], 2);
            }
            if ($price === null) {
                return null;
            }

            $days = isset($json['delivery_days']) ? (int) $json['delivery_days'] : null;
            $label = $tariff === 'self_pickup'
                ? 'В пункт выдачи'.($days ? ', ~'.$days.' дн.' : '')
                : 'По России до двери'.($days ? ', ~'.$days.' дн.' : '');

            return [
                'price' => $price,
                'currency' => 'RUB',
                'type' => 'russia',
                'tariff' => $tariff,
                'delivery_days' => $days,
                'label' => $label,
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::warning('Yandex Platform: '.$e->getMessage());

            return null;
        }
    }

    public function estimateForDestination(
        Tenant $tenant,
        string $destinationAddress,
        float $weightKg = 1.0,
        float $assessedRub = 1000.0,
    ): array {
        $options = [];
        $meta = [
            'test_contour' => $this->isTestContour($tenant),
            'express_configured' => $this->isExpressConfigured($tenant),
            'platform_configured' => $this->isPlatformConfigured($tenant),
            'station_id' => $this->platformStationId($tenant) !== '' ? 'set' : null,
        ];

        if ($this->isPlatformConfigured($tenant)) {
            foreach (['self_pickup', 'time_interval'] as $tariff) {
                $row = $this->checkPriceRussia($tenant, $destinationAddress, $weightKg, $assessedRub, $tariff);
                if ($row !== null) {
                    $options[] = $row;
                }
            }
        }

        if ($this->isExpressConfigured($tenant)) {
            $express = $this->checkPrice($tenant, $destinationAddress, $weightKg);
            if ($express !== null) {
                $options[] = $express;
            }
        }

        usort($options, fn ($a, $b) => $a['price'] <=> $b['price']);

        return [
            'address' => $destinationAddress,
            'options' => $options,
            'min_price' => $options !== [] ? $options[0]['price'] : null,
            'meta' => $meta,
        ];
    }

    protected function extractPrice(array $json): ?float
    {
        foreach (['price_with_vat', 'final_price', 'price', 'total_price_with_vat', 'total_price'] as $key) {
            if (isset($json[$key]) && is_numeric($json[$key])) {
                return round((float) $json[$key], 2);
            }
            if (isset($json[$key]) && is_string($json[$key])) {
                $n = $this->parseMoneyString($json[$key]);
                if ($n !== null) {
                    return $n;
                }
            }
        }

        return null;
    }

    protected function parseMoneyString(string $value): ?float
    {
        if (preg_match('/([\d]+(?:[.,]\d+)?)/', $value, $m)) {
            return round((float) str_replace(',', '.', $m[1]), 2);
        }

        return null;
    }
}
