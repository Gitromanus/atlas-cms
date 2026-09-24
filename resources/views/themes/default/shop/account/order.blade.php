@extends('layouts.shop')

@section('title', 'Заказ №'.$order->number)

@section('content')
    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('account.orders') }}" class="hover:text-primary">Мои заказы</a> → Заказ №{{ $order->number }}
    </nav>

    <h1 class="mb-6 text-3xl font-bold">Заказ №{{ $order->number }}</h1>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-theme bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-lg font-bold">Данные заказа</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Статус</dt><dd class="font-medium">{{ $order->status?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Дата</dt><dd>{{ $order->placed_at?->format('d.m.Y H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Доставка</dt><dd>{{ $order->delivery_method }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Оплата</dt><dd>{{ $order->payment_method }}</dd></div>
                @if ($order->delivery_address)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Адрес</dt><dd class="text-right">{{ $order->delivery_address }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-theme bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-lg font-bold">Состав заказа</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-4 py-2">
                        <dt>
                            {{ $item->product_name }} × {{ $item->quantity }}
                            @if (! empty($item->options))
                                <span class="block text-xs text-slate-500">
                                    @foreach ($item->options as $key => $value)
                                        {{ $key }}: {{ $value }}@if (! $loop->last) · @endif
                                    @endforeach
                                </span>
                            @endif
                        </dt>
                        <dd class="font-medium">{{ number_format($item->total, 0, ',', ' ') }} ₽</dd>
                    </div>
                @endforeach
                <div class="flex justify-between gap-4 pt-3 font-bold">
                    <dt>Итого</dt>
                    <dd>{{ number_format($order->total, 0, ',', ' ') }} ₽</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection