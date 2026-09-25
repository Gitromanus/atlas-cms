@extends('layouts.shop')

@section('title', 'Корзина')

@section('content')
    <h1 class="mb-6 text-3xl font-bold tracking-tight">Корзина</h1>

    @if ($items->isEmpty())
        <div class="rounded-theme border border-dashed border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-3xl">🛒</div>
            <p class="text-lg font-semibold text-slate-800">Корзина пока пуста</p>
            <p class="mt-2 text-sm text-slate-500">Добавьте товары из каталога — они появятся здесь</p>
            <a href="{{ route('catalog.index') }}"
               class="mt-6 inline-block rounded-theme bg-primary px-6 py-3 font-semibold text-white transition hover:opacity-90">
                Перейти в каталог
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-theme bg-white shadow-sm">
            @foreach ($items as $item)
                @php
                    $product = $item->product;
                    $slug = $product?->slug ?: $product?->id;
                    $imgUrl = null;
                    try {
                        $imgUrl = $product?->images?->sortBy('sort_order')->first()?->url
                            ?? $product?->mainImage?->url;
                    } catch (\Throwable) {}
                    $unitPrice = (float) ($product->price ?? 0);
                @endphp
                <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:flex-row sm:items-center">
                    <a href="{{ route('product.show', ['productSlug' => $slug]) }}"
                       class="h-20 w-20 shrink-0 overflow-hidden rounded-theme bg-slate-100">
                        @if ($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-2xl text-slate-300">🛍️</div>
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('product.show', ['productSlug' => $slug]) }}" class="font-semibold hover:text-primary">
                            {{ $product->name }}
                        </a>
                        @if (! empty($item->options))
                            <p class="mt-0.5 text-xs text-slate-500">
                                @foreach ($item->options as $key => $value)
                                    {{ $key }}: <span class="font-medium">{{ $value }}</span>@if (! $loop->last) · @endif
                                @endforeach
                            </p>
                        @endif
                        <p class="text-sm text-slate-500">{{ number_format($unitPrice, 0, ',', ' ') }} ₽ / шт.</p>
                    </div>

                    <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="999"
                               class="w-16 rounded-theme border border-slate-300 px-2 py-1.5 text-center">
                        <button type="submit" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">OK</button>
                    </form>

                    <span class="w-28 text-right font-bold">{{ number_format($unitPrice * $item->quantity, 0, ',', ' ') }} ₽</span>

                    <form method="POST" action="{{ route('cart.remove', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-500" title="Удалить">✕</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-col items-stretch gap-4 sm:items-end">
            <div class="flex items-center justify-between gap-4 sm:justify-end">
                <span class="text-slate-500">Итого:</span>
                <span class="text-2xl font-extrabold">{{ number_format($total, 0, ',', ' ') }} ₽</span>
            </div>

            @php
                $minOrder = $currentTenant?->setting('min_order_sum');
            @endphp
            @if ($minOrder && $total < (float) $minOrder)
                <p class="rounded-theme border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
                    Минимальная сумма заказа — {{ number_format((float) $minOrder, 0, ',', ' ') }} ₽.
                    Добавьте ещё товары на {{ number_format((float) $minOrder - $total, 0, ',', ' ') }} ₽.
                </p>
            @endif

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('cart.clear') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-theme border border-slate-300 px-5 py-2.5 hover:bg-slate-100">Очистить</button>
                </form>
                <a href="{{ route('catalog.index') }}" class="rounded-theme border border-slate-300 px-5 py-2.5 hover:bg-slate-100">
                    Продолжить покупки
                </a>
                <a href="{{ route('checkout.index') }}"
                   class="rounded-theme bg-primary px-6 py-2.5 font-semibold text-white hover:opacity-90">
                    Оформить заказ
                </a>
            </div>
        </div>
    @endif
@endsection
