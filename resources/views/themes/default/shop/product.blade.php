@extends('layouts.shop')

@section('title', $product->name)

@section('content')
    <div class="grid gap-8 md:grid-cols-2">
        <div class="overflow-hidden rounded-theme bg-white shadow-sm">
            @php
                $gallery = $product->images ?? collect();
                $main = $gallery->sortBy('sort_order')->first();
            @endphp
            @if ($main && $main->url)
                <img src="{{ $main->url }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
                @if ($gallery->count() > 1)
                    <div class="flex flex-wrap gap-2 p-3">
                        @foreach ($gallery->sortBy('sort_order') as $image)
                            @if ($image->url)
                                <img src="{{ $image->url }}" alt=""
                                     class="h-16 w-16 rounded-theme object-cover ring-1 ring-slate-200">
                            @endif
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex aspect-square items-center justify-center bg-slate-100 text-6xl text-slate-300">🛍️</div>
            @endif
        </div>

        <div>
            <nav class="mb-2 text-sm text-slate-500">
                <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
                @if ($product->category)
                    → <a href="{{ route('catalog.category', $product->category->slug ?: $product->category->id) }}" class="hover:text-primary">{{ $product->category->name }}</a>
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

            <p class="mt-2 text-sm {{ $product->isAvailable() ? 'text-green-600' : 'text-red-500' }}">
                {{ $product->isAvailable() ? 'В наличии' : 'Нет в наличии' }}
            </p>

            @if ($product->description)
                <div class="mt-6 whitespace-pre-line text-slate-700">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <form method="POST" action="{{ route('cart.add') }}" class="mt-8">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div class="flex max-w-md items-center gap-3">
                    <input type="number" name="quantity" min="1" max="999" value="1"
                           class="w-20 rounded-theme border border-slate-300 py-2 text-center">
                    <button type="submit"
                            class="flex-1 rounded-theme bg-primary px-5 py-2 font-semibold text-white hover:opacity-90">
                        В корзину
                    </button>
                </div>
            </form>

            @php
                $plainFeatures = ($product->features ?? collect())->filter(fn ($f) => ! $f->is_variant);
            @endphp
            @if ($plainFeatures->isNotEmpty())
                <h2 class="mt-10 mb-3 text-xl font-bold">Характеристики</h2>
                <dl class="divide-y divide-slate-200 rounded-theme bg-white shadow-sm">
                    @foreach ($plainFeatures as $feature)
                        <div class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="text-slate-500">{{ $feature->name }}</dt>
                            <dd class="font-medium">{{ $feature->value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    @if (($related ?? collect())->isNotEmpty())
        <section class="mt-12">
            <h2 class="mb-4 text-2xl font-bold">Похожие товары</h2>
            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                @foreach ($related as $item)
                    @include('shop.partials.product-card', ['product' => $item])
                @endforeach
            </div>
        </section>
    @endif
@endsection
