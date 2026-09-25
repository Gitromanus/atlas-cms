@extends('layouts.shop')

@section('title', 'Заказ №'.$order->number)

@section('content')
    <a href="{{ route('account.orders') }}" class="mb-4 inline-block text-sm text-primary hover:underline">← Все заказы</a>

    @php
        $statusName = $order->status?->name ?? '—';
        $badge = match ($statusName) {
            'Новый' => 'bg-sky-100 text-sky-800',
            'В обработке' => 'bg-amber-100 text-amber-800',
            'Выполнен', 'Доставлен' => 'bg-emerald-100 text-emerald-800',
            'Отменён' => 'bg-red-100 text-red-800',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Заказ №{{ $order->number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $order->placed_at?->format('d.m.Y H:i') }}</p>
        </div>
        <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $badge }}">{{ $statusName }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-theme bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Товар</th>
                    <th class="px-4 py-3 text-center">Кол-во</th>
                    <th class="px-4 py-3 text-right">Сумма</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b border-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $item->product_name }}</p>
                            @if ($item->sku)
                                <p class="text-xs text-slate-400">{{ $item->sku }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($item->total, 0, ',', ' ') }} ₽</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            <div class="rounded-theme bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Итого</p>
                <p class="text-2xl font-extrabold">{{ number_format($order->total, 0, ',', ' ') }} ₽</p>
                @if ($order->is_paid)
                    <p class="mt-2 text-sm font-medium text-emerald-600">Оплачен</p>
                @endif
            </div>
            <div class="rounded-theme bg-white p-5 text-sm shadow-sm">
                <p class="font-semibold">Доставка</p>
                <p class="mt-1 text-slate-600">{{ $order->delivery_method ?? '—' }}</p>
                @if ($order->delivery_address)
                    <p class="mt-1 text-slate-600">{{ $order->delivery_address }}</p>
                @endif
                <p class="mt-4 font-semibold">Оплата</p>
                <p class="mt-1 text-slate-600">{{ $order->payment_method ?? '—' }}</p>
            </div>
        </div>
    </div>
@endsection
