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
        ]);

        $itemsTotal = (float) $this->cart->total();
        $method = null;

        if (! empty($validated['delivery_method_id'])) {
            $method = DeliveryMethod::query()->active()->whereKey($validated['delivery_method_id'])->first();
        }

        $code = strtolower((string) ($method?->code ?? $validated['delivery_method'] ?? ''));
        $useExpress = in_array($code, ['yandex', 'yandex_delivery', 'yandex-delivery', 'yandex_express'], true);
        $useRussia = in_array($code, ['yandex_russia', 'yandex_ndd', 'yandex_platform', 'yandex-russia'], true);

        if ($useExpress || $useRussia) {
            if (! $this->yandex->isConfigured($tenant) && ! $this->yandex->isPlatformConfigured($tenant)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Яндекс Доставка не настроена',
                    'price' => $method ? (float) $method->costFor($itemsTotal) : null,
                ]);
            }

            $address = trim((string) ($validated['address'] ?? ''));
            if ($address === '') {
                return response()->json(['ok' => false, 'message' => 'Укажите адрес доставки для расчёта', 'price' => null]);
            }

            $qty = max(1, (int) $this->cart->count());
            $weight = max(0.5, $qty * 0.5);

            $result = $useRussia
                ? $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal))
                : $this->yandex->checkPrice($tenant, $address, $weight);

            if ($result === null && $useExpress) {
                $result = $this->yandex->checkPriceRussia($tenant, $address, $weight, max(500, $itemsTotal));
            }

            if ($result === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Не удалось рассчитать. Проверьте адрес.',
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

        return response()->json([
            'ok' => true,
            'city' => $city,
            'region' => $geo['region'] ?? null,
            'geo_source' => $geo['source'] ?? null,
            'address' => $estimate['address'],
            'min_price' => $estimate['min_price'],
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
            'message' => $estimate['options'] === []
                ? 'Не удалось рассчитать доставку для этого города'
                : null,
        ]);
    }
}
