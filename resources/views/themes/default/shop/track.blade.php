@extends('layouts.shop')

@section('title', 'Статус заказа')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-2 text-3xl font-bold tracking-tight">Статус заказа</h1>
        <p class="mb-6 text-slate-500">Введите номер заказа и телефон, указанный при оформлении.</p>

        <form method="POST" action="{{ route('order.track.lookup') }}" class="space-y-4 rounded-theme bg-white p-6 shadow-sm">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium">Номер заказа</label>
                <input type="text" name="number" value="{{ old('number', $number) }}" required
                       placeholder="Например, 000001"
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Телефон</label>
                <input type="tel" name="phone" value="{{ old('phone', $phone) }}" required
                       placeholder="+7…"
                       class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
            </div>
            <button type="submit" class="w-full rounded-theme bg-primary px-5 py-3 font-semibold text-white hover:opacity-90">
                Проверить
            </button>
        </form>

        @if ($searched)
            <div class="mt-8">
                @if ($order)
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
                    <div class="rounded-theme bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-500">Заказ</p>
                                <p class="text-xl font-bold">№{{ $order->number }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $badge }}">{{ $statusName }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">
                            от {{ $order->placed_at?->format('d.m.Y H:i') ?? '—' }}
                        </p>
                        <p class="mt-4 text-2xl font-extrabold">{{ number_format($order->total, 0, ',', ' ') }} ₽</p>

                        <ul class="mt-6 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            @foreach ($order->items as $item)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                                    <span class="shrink-0 font-medium">{{ number_format($item->total, 0, ',', ' ') }} ₽</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="rounded-theme border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        Заказ не найден. Проверьте номер и телефон.
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
