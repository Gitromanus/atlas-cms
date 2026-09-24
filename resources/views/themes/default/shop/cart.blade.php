@extends('layouts.shop')

@section('title', 'Корзина')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Корзина</h1>

    @if ($items->isEmpty())
        <div class="rounded-theme bg-white p-10 text-center shadow-sm">
            <p class="mb-4 text-slate-500">Ваша корзина пуста</p>
            <a href="{{ route('catalog.index') }}" class="inline-block rounded-theme bg-primary px-6 py-3 font-semibold text-white hover:opacity-90">
                Перейти в каталог
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-theme bg-white shadow-sm">
            @foreach ($items as $item)
                <div class="flex items-center gap-4 border-b border-slate-100 p-4">
                    <a href="{{ route('product.show', $item->product->slug ?: $item->product->id) }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-theme bg-slate-100">
                        @if ($item->product->mainImage?->url)
                            <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('product.show', $item->product->slug ?: $item->product->id) }}" class="font-semibold hover:text-primary">
                            {{ $item->product->name }}
                        </a>
                        @if (! empty($item->options))
                            <p class="mt-0.5 text-xs text-slate-500">
                                @foreach ($item->options as $key => $value)
                                    {{ $key }}: <span class="font-medium">{{ $value }}</span>@if (! $loop->last) · @endif
                                @endforeach
                            </p>
                        @endif
                        <p class="text-sm text-slate-500">{{ number_format($item->product->price ?? 0, 0, ',', ' ') }} ₽ / шт.</p>
                    </div>

                    <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="999"
                               class="w-16 rounded-theme border border-slate-300 px-2 py-1.5 text-center">
                        <button type="submit" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">OK</button>
                    </form>

                    <span class="w-28 text-right font-bold">{{ number_format(($item->product->price ?? 0) * $item->quantity, 0, ',', ' ') }} ₽</span>

                    <form method="POST" action="{{ route('cart.remove', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-500" title="Удалить">✕</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-col items-end gap-4">
            <div class="flex items-center gap-4">
                <span class="text-slate-500">Итого:</span>
                <span class="text-2xl font-extrabold">{{ number_format($total, 0, ',', ' ') }} ₽</span>
            </div>

            <div class="flex gap-3">
                <form method="POST" action="{{ route('cart.clear') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-theme border border-slate-300 px-5 py-2.5 hover:bg-slate-100">Очистить</button>
                </form>
                <a href="{{ route('checkout.index') }}" class="rounded-theme bg-primary px-6 py-2.5 font-semibold text-white hover:opacity-90">
                    Оформить заказ
                </a>
            </div>
        </div>
    @endif
@endsection