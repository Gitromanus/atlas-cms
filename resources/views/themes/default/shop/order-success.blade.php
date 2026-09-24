@extends('layouts.shop')

@section('title', 'Заказ оформлен')

@section('content')
    <div class="mx-auto max-w-2xl rounded-theme bg-white p-8 text-center shadow-sm">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl">✅</div>
        <h1 class="text-2xl font-bold">Заказ №{{ $order->number }} принят</h1>
        <p class="mt-3 text-slate-500">
            Мы свяжемся с вами для подтверждения заказа.
            Номер для отслеживания: <strong class="text-slate-800">{{ $order->number }}</strong>
        </p>

        <dl class="mt-8 divide-y divide-slate-200 rounded-theme border border-slate-200 text-left">
            @foreach ($order->items as $item)
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt>{{ $item->product_name }} × {{ $item->quantity }}</dt>
                    <dd class="font-medium">{{ number_format($item->total, 0, ',', ' ') }} ₽</dd>
                </div>
            @endforeach
            <div class="flex justify-between gap-4 bg-slate-50 px-4 py-3 font-bold">
                <dt>Итого</dt>
                <dd>{{ number_format($order->total, 0, ',', ' ') }} ₽</dd>
            </div>
        </dl>

        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('home') }}" class="rounded-theme bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">На главную</a>
            <a href="{{ route('catalog.index') }}" class="rounded-theme border border-slate-300 px-6 py-3 font-semibold hover:bg-slate-100">Продолжить покупки</a>
        </div>
    </div>
@endsection