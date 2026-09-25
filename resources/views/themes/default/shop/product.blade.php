@extends('layouts.shop')

@section('title', $product->name)

@section('content')
    <div class="grid gap-8 md:grid-cols-2">
        <div class="overflow-hidden rounded-theme bg-white shadow-sm">
            @php $img = $product->images->sortBy('sort_order')->first(); @endphp
            @if ($img?->url)
                <img src="{{ $img->url }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
            @else
                <div class="flex aspect-square items-center justify-center bg-slate-100 text-6xl text-slate-300">🛍️</div>
            @endif
        </div>
        <div>
            <nav class="mb-2 text-sm text-slate-500">
                <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
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
                    <button type="submit" class="flex-1 rounded-theme bg-primary px-5 py-2 font-semibold text-white">
                        В корзину
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
