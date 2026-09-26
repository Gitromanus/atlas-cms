<?php

namespace App\Services\Delivery;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент B2B API Яндекс Доставки (Express).
 *
 * Документация: POST /b2b/cargo/integration/v2/check-price
 * Auth: Authorization: Bearer <OAuth-токен>
 */
class YandexDeliveryService
{
    protected string $baseUrl = 'https://b2b.taxi.yandex.net';

    public function isConfigured(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return filled($tenant->setting('yandex_delivery_token'))
            && filled($tenant->setting('yandex_delivery_source_address'));
    }

    /**
     * Предварительный расчёт стоимости курьерской доставки.
     *
     * @return array{price: float, currency: string, taxi_class: string, raw?: array}|null
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
        $token = (string) $tenant->setting('yandex_delivery_token');
        $sourceAddress = (string) $tenant->setting('yandex_delivery_source_address');
        $taxiClass = (string) ($tenant->setting('yandex_delivery_taxi_class') ?: 'express');

        if ($token === '' || $sourceAddress === '' || trim($destinationAddress) === '') {
            return null;
        }

        $sourcePoint = [
            'id' => 1,
            'fullname' => $sourceAddress,
        ];
        $srcLon = $tenant->setting('yandex_delivery_source_lon');
        $srcLat = $tenant->setting('yandex_delivery_source_lat');
        if ($srcLon !== null && $srcLat !== null && $srcLon !== '' && $srcLat !== '') {
            $sourcePoint['coordinates'] = [(float) $srcLon, (float) $srcLat];
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
                'taxi_class' => in_array($taxiClass, ['courier', 'express', 'cargo'], true)
                    ? $taxiClass
                    : 'express',
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['Accept-Language' => 'ru'])
                ->timeout(15)
                ->post($this->baseUrl.'/b2b/cargo/integration/v2/check-price', $payload);

            if (! $response->successful()) {
                Log::warning('Yandex Delivery check-price failed', [
                    'status' => $response->status(),
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

            return [
                'price' => $price,
                'currency' => 'RUB',
                'taxi_class' => $taxiClass,
                'raw' => $json,
            ];
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
