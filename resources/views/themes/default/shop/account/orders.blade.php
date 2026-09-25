@extends('layouts.shop')

@section('title', 'Мои заказы')

@section('content')
    <h1 class="mb-6 text-3xl font-bold tracking-tight">Мои заказы</h1>

    @if ($orders->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
            <p class="text-lg font-semibold text-slate-800">Заказов пока нет</p>
            <p class="mt-2 text-sm text-slate-500">Когда оформите заказ, он появится здесь</p>
            <a href="{{ route('catalog.index') }}"
               class="mt-6 inline-block rounded-xl bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">
                В каталог
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">№</th>
                    <th class="px-4 py-3">Дата</th>
                    <th class="px-4 py-3">Статус</th>
                    <th class="px-4 py-3 text-right">Сумма</th>
                    <th class="px-4 py-3"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    @php
                        $statusName = $order->status?->name ?? '—';
                        $badge = 'bg-slate-100 text-slate-700';
                        if ($statusName === 'Новый') $badge = 'bg-sky-100 text-sky-800';
                        elseif ($statusName === 'В обработке') $badge = 'bg-amber-100 text-amber-800';
                        elseif (in_array($statusName, ['Выполнен', 'Доставлен'], true)) $badge = 'bg-emerald-100 text-emerald-800';
                        elseif ($statusName === 'Отменён') $badge = 'bg-red-100 text-red-800';
                    @endphp
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $order->number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->placed_at?->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">{{ $statusName }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $order->total, 0, ',', ' ') }} ₽</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('account.orders.show', ['order' => $order->id]) }}" class="font-medium text-primary hover:underline">Подробнее</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
