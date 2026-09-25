@extends('layouts.shop')

@section('title', $product->name)

@section('content')
    @php
        $gallery = $product->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        $main = $gallery->first();
        $price = $product->price;
        $stock = null;
        try {
            if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
                $stock = (float) $product->variants->sum('quantity');
            } else {
                $stock = (float) $product->stocks->sum('quantity');
            }
        } catch (\Throwable) {}
        $props = $product->features->where('is_variant', false)->values();
    @endphp

    <nav class="mb-6 text-sm text-slate-500">
        <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
        @if ($product->category)
            <span class="mx-1">/</span>
            <a href="{{ route('catalog.category', ['categorySlug' => $product->category->slug ?: $product->category->id]) }}"
               class="hover:text-primary">{{ $product->category->name }}</a>
        @endif
        <span class="mx-1">/</span>
        <span class="text-slate-800">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-2">
        <div>
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                @if ($main?->url)
                    <img id="product-main-image" src="{{ $main->url }}" alt="{{ $product->name }}"
                         class="aspect-square w-full object-cover">
                @else
                    <div class="flex aspect-square items-center justify-center bg-slate-50 text-7xl text-slate-200">🛍️</div>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($gallery as $img)
                        @continue(! $img->url)
                        <button type="button" data-src="{{ $img->url }}"
                                class="product-thumb h-20 w-20 shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent transition hover:ring-primary focus:outline-none focus:ring-primary">
                            <img src="{{ $img->url }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
                <script>
                    document.querySelectorAll('.product-thumb').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var main = document.getElementById('product-main-image');
                            if (main && btn.dataset.src) main.src = btn.dataset.src;
                        });
                    });
                </script>
            @endif
        </div>

        <div class="flex flex-col">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">{{ $product->name }}</h1>
            @if ($product->sku)
                <p class="mt-2 text-sm text-slate-500">Артикул: <span class="font-medium text-slate-700">{{ $product->sku }}</span></p>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <p class="text-4xl font-black tracking-tight text-primary">
                    {{ $price !== null ? number_format((float) $price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
                </p>
                @if ($stock !== null)
                    @if ($stock > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            В наличии · {{ rtrim(rtrim(number_format($stock, 2, ',', ' '), '0'), ',') }} {{ $product->unit ?: 'шт' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-600">
                            Нет в наличии
                        </span>
                    @endif
                @endif
            </div>

            @if ($product->description)
                <div class="mt-6 max-w-none text-slate-700">
                    <div class="whitespace-pre-line leading-relaxed">{{ $product->description }}</div>
                </div>
            @endif

            @if ($props->isNotEmpty())
                <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">Характеристики</div>
                    <dl class="divide-y divide-slate-100 text-sm">
                        @foreach ($props as $f)
                            <div class="grid grid-cols-2 gap-2 px-4 py-2.5 sm:grid-cols-5">
                                <dt class="text-slate-500 sm:col-span-2">{{ $f->name }}</dt>
                                <dd class="font-medium text-slate-900 sm:col-span-3">{{ $f->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <form method="POST" action="{{ route('cart.add') }}" class="mt-8">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <label class="sr-only" for="qty">Количество</label>
                    <input id="qty" type="number" name="quantity" min="1" max="999" value="1"
                           class="w-24 rounded-xl border border-slate-300 py-3 text-center text-lg font-semibold focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <button type="submit"
                            class="flex-1 rounded-xl bg-primary px-8 py-3.5 text-base font-bold text-white shadow-lg shadow-primary/25 transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                            @if($stock !== null && $stock <= 0) disabled @endif>
                        {{ $stock !== null && $stock <= 0 ? 'Нет в наличии' : 'Добавить в корзину' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (isset($related) && $related->isNotEmpty())
        <section class="mt-20">
            <div class="mb-6 flex items-end justify-between gap-3">
                <h2 class="text-2xl font-bold tracking-tight">Вам может понравиться</h2>
                <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-primary hover:underline">Весь каталог →</a>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($related as $rel)
                    @include('shop.partials.product-card', ['product' => $rel])
                @endforeach
            </div>
        </section>
    @endif
@endsection
