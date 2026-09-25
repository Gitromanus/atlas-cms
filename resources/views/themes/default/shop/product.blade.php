@extends('layouts.shop')

@section('title', $product->name)

@section('content')
    @php
        $gallery = $product->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        $main = $gallery->first();
    @endphp
    <div class="grid gap-8 md:grid-cols-2">
        <div>
            <div class="overflow-hidden rounded-theme bg-white shadow-sm">
                @if ($main?->url)
                    <img id="product-main-image"
                         src="{{ $main->url }}"
                         alt="{{ $product->name }}"
                         class="aspect-square w-full object-cover">
                @else
                    <div class="flex aspect-square items-center justify-center bg-slate-100 text-6xl text-slate-300">🛍️</div>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($gallery as $img)
                        @continue(! $img->url)
                        <button type="button"
                                class="product-thumb overflow-hidden rounded-lg ring-1 ring-slate-200 hover:ring-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                data-src="{{ $img->url }}">
                            <img src="{{ $img->url }}" alt="" class="h-16 w-16 object-cover">
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
        <div>
            <nav class="mb-2 text-sm text-slate-500">
                <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
                @if ($product->category)
                    <span class="mx-1">/</span>
                    <a href="{{ route('catalog.category', ['categorySlug' => $product->category->slug ?: $product->category->id]) }}"
                       class="hover:text-primary">{{ $product->category->name }}</a>
                @endif
            </nav>
            <h1 class="text-3xl font-bold">{{ $product->name }}</h1>
            @if ($product->sku)
                <p class="mt-1 text-sm text-slate-500">Артикул: {{ $product->sku }}</p>
            @endif
            <p class="mt-4 text-3xl font-extrabold text-primary">
                @php $price = $product->price; @endphp
                {{ $price !== null ? number_format((float) $price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
            </p>
            @if ($product->description)
                <div class="mt-6 whitespace-pre-line text-slate-700">{{ $product->description }}</div>
            @endif
            <form method="POST" action="{{ route('cart.add') }}" class="mt-8">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div class="flex max-w-md items-center gap-3">
                    <input type="number" name="quantity" min="1" max="999" value="1"
                           class="w-20 rounded-theme border border-slate-300 py-2 text-center">
                    <button type="submit" class="flex-1 rounded-theme bg-primary px-5 py-2.5 font-semibold text-white hover:opacity-90">
                        В корзину
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (isset($related) && $related->isNotEmpty())
        <section class="mt-16">
            <h2 class="mb-6 text-2xl font-bold tracking-tight">Похожие товары</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($related as $rel)
                    @include('shop.partials.product-card', ['product' => $rel])
                @endforeach
            </div>
        </section>
    @endif
@endsection
