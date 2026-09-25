<x-mail::message>
# Спасибо за заказ!

Здравствуйте{{ $order->customer_name ? ', '.$order->customer_name : '' }}!

Ваш заказ **№{{ $order->number }}** в магазине **{{ $tenant->name }}** принят.

**Сумма:** {{ number_format($order->total, 0, ',', ' ') }} ₽

## Состав
@foreach ($order->items as $item)
- {{ $item->product_name }} × {{ $item->quantity }} — {{ number_format($item->total, 0, ',', ' ') }} ₽
@endforeach

@if ($tenant->setting('phone'))
По вопросам: {{ $tenant->setting('phone') }}
@endif

<x-mail::button :url="$trackUrl">
Статус заказа
</x-mail::button>

С уважением,<br>
{{ $tenant->name }}
</x-mail::message>
