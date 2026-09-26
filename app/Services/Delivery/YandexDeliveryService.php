<?php

namespace App\Services\Delivery;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент B2B API Яндекс Доставки (Express / check-price).
 *
 * Боевой хост: https://b2b.taxi.yandex.net
 * Тестовый:    https://b2b.taxi.tst.yandex.net (только Москва)
 *
 * Auth: Authorization: Bearer <OAuth-токен>
 */
class YandexDeliveryService
{
    public function isConfigured(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        [$token, $source] = $this->credentials($tenant);

        return $token !== '' && $source !== '';
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: ?float, 4: ?float, 5: string}
     */
    protected function credentials(Tenant $tenant): array
    {
        $token = trim((string) $tenant->setting('yandex_delivery_token', ''));
        $source = trim((string) $tenant->setting('yandex_delivery_source_address', ''));
        $taxiClass = (string) ($tenant->setting('yandex_delivery_taxi_class') ?: 'express');
        $lon = $tenant->setting('yandex_delivery_source_lon');
        $lat = $tenant->setting('yandex_delivery_source_lat');
        $testMode = (bool) $tenant->setting('yandex_delivery_test_mode', false);

        $testToken = (string) config('services.yandex_delivery.test_token');
        $testBase = rtrim((string) config('services.yandex_delivery.test_base_url'), '/');
        $prodBase = rtrim((string) config('services.yandex_delivery.prod_base_url'), '/');

        $useTest = $testMode
            || ($token !== '' && $token === $testToken)
            || ($token === '' && config('services.yandex_delivery.fallback_to_test'));

        if ($token === '' && $useTest) {
            $token = $testToken;
        }

        if ($source === '' && $useTest) {
            $source = (string) config('services.yandex_delivery.test_source_address');
            $lon = config('services.yandex_delivery.test_source_lon');
            $lat = config('services.yandex_delivery.test_source_lat');
        }

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
        ];
    }

    /**
     * @return array{price: float, currency: string, taxi_class: string, distance_meters?: float, eta?: float, raw?: array}|null
     */
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

        $sourcePoint = [
            'id' => 1,
            'fullname' => $sourceAddress,
        ];
        if ($srcLon !== null && $srcLat !== null) {
            $sourcePoint['coordinates'] = [$srcLon, $srcLat];
        }

        $destPoint = [
            'id' => 2,
            'fullname' => trim($destinationAddress),
        ];

        $payload = [
            'items' => [
                [
                    'size' => [
                        'length' => max(0.01, (float) $lengthM),
                        'width' => max(0.01, (float) $widthM),
                        'height' => max(0.01, (float) $heightM),
                    ],
                    'weight' => max(0.1, $weightKg),
                    'quantity' => max(1, $quantity),
                    'pickup_point' => 1,
                    'dropoff_point' => 2,
                ],
            ],
            'route_points' => [$sourcePoint, $destPoint],
            'requirements' => [
                'taxi_class' => $taxiClass,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['Accept-Language' => 'ru'])
                ->timeout(15)
                ->post($baseUrl.'/b2b/cargo/integration/v2/check-price', $payload);

            if (! $response->successful()) {
                Log::warning('Yandex Delivery check-price failed', [
                    'status' => $response->status(),
                    'base' => $baseUrl,
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $json = $response->json() ?? [];
            $price = $this->extractPrice($json);

            if ($price === null) {
                Log::warning('Yandex Delivery: no price in response', ['json' => $json]);

                return null;
            }

            $result = [
                'price' => $price,
                'currency' => 'RUB',
                'taxi_class' => $taxiClass,
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
            Log::warning('Yandex Delivery exception: '.$e->getMessage());

            return null;
        }
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

        if (isset($json['offers'][0]['price'])) {
            $p = $json['offers'][0]['price'];
            if (is_array($p)) {
                foreach (['total_price_with_vat', 'total_price', 'base_price'] as $k) {
                    if (isset($p[$k])) {
                        $n = is_numeric($p[$k]) ? (float) $p[$k] : $this->parseMoneyString((string) $p[$k]);
                        if ($n !== null) {
                            return round($n, 2);
                        }
                    }
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
