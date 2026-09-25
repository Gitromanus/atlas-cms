<x-mail::message>
# Заказ №{{ $order->number }}
Статус вашего заказа изменён на: **{{ $statusName }}**.
**Итого:** {{ number_format((float) $order->total, 0, ',', ' ') }} ₽
Спасибо за покупку в «{{ $tenant->name }}»!
</x-mail::message>
