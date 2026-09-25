<x-mail::message>
# Новый заказ №{{ $order->number }}

**Магазин:** {{ $tenant->name }}

**Покупатель:** {{ $order->customer_name }}  
**Телефон:** {{ $order->customer_phone ?? '—' }}  
**Email:** {{ $order->customer_email ?? '—' }}

**Сумма:** {{ number_format($order->total, 0, ',', ' ') }} ₽  
**Доставка:** {{ $order->delivery_method ?? '—' }}  
**Адрес:** {{ $order->delivery_address ?? '—' }}  
**Оплата:** {{ $order->payment_method ?? '—' }}

@if ($order->comment)
**Комментарий:** {{ $order->comment }}
@endif

## Состав
@foreach ($order->items as $item)
- {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format($item->total, 0, ',', ' ') }} ₽
@endforeach

<x-mail::button :url="$adminUrl">
Открыть в админке
</x-mail::button>

Спасибо,<br>
{{ config('app.name') }}
</x-mail::message>
