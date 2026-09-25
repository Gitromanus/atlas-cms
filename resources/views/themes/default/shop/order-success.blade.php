@extends('layouts.shop')

@section('title', 'Заказ оформлен')

@section('content')
    <div class="mx-auto max-w-lg text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl">✓</div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Заказ принят</h1>
        <p class="mt-3 text-slate-600">
            Номер заказа <span class="font-bold text-slate-900">№{{ $order->number }}</span>
        </p>
        <p class="mt-1 text-2xl font-extrabold text-primary">{{ number_format($order->total, 0, ',', ' ') }} ₽</p>

        @if ($order->customer_email)
            <p class="mt-4 text-sm text-slate-500">Подтверждение отправили на {{ $order->customer_email }}</p>
        @endif

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('order.track', ['number' => $order->number]) }}"
               class="rounded-theme bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">
                Следить за статусом
            </a>
            <a href="{{ route('catalog.index') }}"
               class="rounded-theme border border-slate-300 px-6 py-3 font-semibold hover:bg-slate-100">
                В каталог
            </a>
        </div>

        <ul class="mt-10 space-y-2 rounded-theme bg-white p-5 text-left text-sm shadow-sm">
            @foreach ($order->items as $item)
                <li class="flex justify-between gap-3 border-b border-slate-50 pb-2 last:border-0 last:pb-0">
                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <span class="font-medium">{{ number_format($item->total, 0, ',', ' ') }} ₽</span>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
