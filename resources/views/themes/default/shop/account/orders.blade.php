@extends('layouts.shop')

@section('title', 'Мои заказы')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Мои заказы</h1>

    @if ($orders->isEmpty())
        <div class="rounded-theme bg-white p-10 text-center shadow-sm">
            <p class="text-slate-500">У вас пока нет заказов</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-theme bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
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
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $order->number }}</td>
                        <td class="px-4 py-3">{{ $order->placed_at?->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $order->status?->name }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($order->total, 0, ',', ' ') }} ₽</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('account.orders.show', $order) }}" class="text-primary hover:underline">Подробнее</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection