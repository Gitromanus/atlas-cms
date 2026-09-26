<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Services\Cart\CartService;
use App\Services\Delivery\YandexDeliveryService;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected YandexDeliveryService $yandex,
        protected CartService $cart,
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
            $method = DeliveryMethod::query()
                ->active()
                ->whereKey($validated['delivery_method_id'])
                ->first();
        }

        $useYandex = $method
            && in_array(strtolower((string) $method->code), ['yandex', 'yandex_delivery', 'yandex-delivery'], true);

        if ($useYandex || (! $method && ($validated['delivery_method'] ?? '') === 'yandex')) {
            if (! $this->yandex->isConfigured($tenant)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Яндекс Доставка не настроена. Укажите токен и адрес склада в настройках магазина.',
                    'price' => $method ? (float) $method->costFor($itemsTotal) : null,
                ]);
            }

            $address = trim((string) ($validated['address'] ?? ''));
            if ($address === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Укажите адрес доставки для расчёта',
                    'price' => null,
                ]);
            }

            $qty = max(1, (int) $this->cart->count());
            $weight = max(0.5, $qty * 0.5);

            $result = $this->yandex->checkPrice($tenant, $address, $weight);

            if ($result === null) {
                $fallback = $method ? (float) $method->costFor($itemsTotal) : null;

                return response()->json([
                    'ok' => false,
                    'message' => 'Не удалось рассчитать через Яндекс Доставку. Проверьте адрес или токен.',
                    'price' => $fallback,
                    'source' => 'fallback',
                ]);
            }

            return response()->json([
                'ok' => true,
                'price' => $result['price'],
                'currency' => $result['currency'],
                'taxi_class' => $result['taxi_class'],
                'source' => 'yandex',
                'message' => 'Стоимость по тарифу Яндекс Доставки',
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

        $code = $validated['delivery_method'] ?? 'pickup';
        $price = match ($code) {
            'pickup' => 0.0,
            'courier' => 300.0,
            'post' => 350.0,
            default => 0.0,
        };

        return response()->json([
            'ok' => true,
            'price' => $price,
            'currency' => 'RUB',
            'source' => 'legacy',
        ]);
    }
}
