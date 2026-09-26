<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Delivery\YandexDeliveryService;
use App\Services\Geo\GeoIpService;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected YandexDeliveryService $yandex,
        protected CartService $cart,
        protected GeoIpService $geo,
    ) {}

    public function calculate(Request $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->current();
        if ($tenant === null) {
            return response()->json(['ok' => false, 'message' => 'Магазин не найден'], 404);
        }

        $validated = $request->validate([
            'delivery_method_id' => ['nullable', 'integer'],
            'delivery_method' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tariff' => ['nullable', 'string', 'in:self_pickup,time_interval,express,auto'],
        ]);

        $itemsTotal = (float) $this->cart->total();
        $method = null;

        if (! empty($validated['delivery_method_id'])) {
            $method = DeliveryMethod::query()->active()->whereKey($validated['delivery_method_id'])->first();
        }

        $code = strtolower((string) ($method?->code ?? $validated['delivery_method'] ?? ''));
        $isYandex = str_starts_with($code, 'yandex')
            || in_array($code, ['yandex', 'yandex_delivery', 'yandex-delivery', 'yandex_express', 'yandex_russia', 'yandex_pvz', 'yandex_ndd', 'yandex_platform'], true);

        if ($isYandex) {
            if (! $this->yandex->isConfigured($tenant)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Яндекс Доставка не настроена (нужны токен и ID станции или адрес склада)',
                    'price' => $method ? (float) $method->costFor($itemsTotal) : null,
                ]);
            }

            $address = trim((string) ($validated['address'] ?? ''));
            if ($address === '') {
                return response()->json(['ok' => false, 'message' => 'Укажите адрес или город для расчёта', 'price' => null]);
            }

            $qty = max(1, (int) $this->cart->count());
            $weight = max(0.5, $qty * 0.5);
            $tariff = (string) ($validated['tariff'] ?? 'auto');

            if ($tariff === 'auto' || $tariff === '') {
                $tariff = match (true) {
                    in_array($code, ['yandex_pvz', 'yandex_pickup'], true) => 'self_pickup',
                    in_array($code, ['yandex_russia', 'yandex_ndd', 'yandex_platform'], true) => 'time_interval',
                    $code === 'yandex_express' => 'express',
                    default => 'self_pickup',
                };
            }

            $result = null;
            if ($tariff === 'express') {
                $result = $this->yandex->checkPrice($tenant, $address, $weight);
                if ($result === null) {
                    $result = $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal), 'time_interval');
                }
            } elseif (in_array($tariff, ['self_pickup', 'time_interval'], true)) {
                $result = $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal), $tariff);
            } else {
                $result = $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal), 'self_pickup');
                if ($result === null) {
                    $result = $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal), 'time_interval');
                }
                if ($result === null) {
                    $result = $this->yandex->checkPrice($tenant, $address, $weight);
                }
            }

            if ($result === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Не удалось рассчитать. Проверьте токен, ID станции и адрес.',
                    'price' => $method ? (float) $method->costFor($itemsTotal) : null,
                    'source' => 'fallback',
                ]);
            }

            return response()->json([
                'ok' => true,
                'price' => $result['price'],
                'currency' => $result['currency'] ?? 'RUB',
                'type' => $result['type'] ?? 'yandex',
                'label' => $result['label'] ?? null,
                'delivery_days' => $result['delivery_days'] ?? null,
                'tariff' => $tariff,
                'source' => 'yandex',
                'message' => $result['label'] ?? 'Стоимость по тарифу Яндекс Доставки',
            ]);
        }

        if ($method) {
            $price = (float) $method->costFor($itemsTotal);

            return response()->json([
                'ok' => true,
                'price' => $price,
                'currency' => 'RUB',
                'source' => 'fixed',
                'free_from' => $method->free_from !== null ? (float) $method->free_from : null,
                'message' => $price <= 0 ? 'Бесплатная доставка' : null,
            ]);
        }

        $legacy = $validated['delivery_method'] ?? 'pickup';
        $price = match ($legacy) {
            'pickup' => 0.0,
            'courier' => 300.0,
            'post' => 350.0,
            default => 0.0,
        };

        return response()->json(['ok' => true, 'price' => $price, 'currency' => 'RUB', 'source' => 'legacy']);
    }

    public function estimate(Request $request): JsonResponse
    {
        $tenant = app(TenantContext::class)->current();
        if ($tenant === null) {
            return response()->json(['ok' => false, 'message' => 'Магазин не найден'], 404);
        }

        $validated = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $geo = $this->geo->cityFromRequest($request);
        $city = $geo['city'];

        $address = trim((string) ($validated['address'] ?? ''));
        if ($address === '') {
            $address = $this->geo->destinationAddress($city);
        }

        $weight = 1.0;
        $assessed = 1000.0;

        if (! empty($validated['product_id'])) {
            $product = Product::query()->active()->find($validated['product_id']);
            if ($product) {
                $assessed = max(500.0, (float) ($product->price ?? 1000));
                $weight = 0.8;
            }
        }

        $estimate = $this->yandex->estimateForDestination($tenant, $address, $weight, $assessed);
        $meta = $estimate['meta'] ?? [];

        $hint = null;
        if ($estimate['options'] === []) {
            if (empty($meta['platform_configured']) && empty($meta['express_configured'])) {
                $hint = 'В настройках укажите OAuth-токен и ID станции отгрузки.';
            } elseif (empty($meta['platform_configured'])) {
                $hint = 'Нет ID станции. Вставьте platform_station_id в настройках магазина.';
            } elseif (! empty($meta['test_contour'])) {
                $hint = 'Тестовый контур. Проверьте город назначения.';
            } else {
                $hint = 'API не вернул тарифы. Проверьте токен и ID станции.';
            }
        }

        return response()->json([
            'ok' => true,
            'city' => $city,
            'region' => $geo['region'] ?? null,
            'geo_source' => $geo['source'] ?? null,
            'address' => $estimate['address'],
            'min_price' => $estimate['min_price'],
            'test_contour' => (bool) ($meta['test_contour'] ?? false),
            'options' => array_map(static function (array $o) {
                return [
                    'type' => $o['type'] ?? null,
                    'label' => $o['label'] ?? null,
                    'price' => $o['price'],
                    'currency' => $o['currency'] ?? 'RUB',
                    'delivery_days' => $o['delivery_days'] ?? null,
                    'eta' => $o['eta'] ?? null,
                    'tariff' => $o['tariff'] ?? null,
                ];
            }, $estimate['options']),
            'message' => $hint,
        ]);
    }
}
