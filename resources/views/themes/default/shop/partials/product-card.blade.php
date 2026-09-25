@php
    $imgUrl = null;
    try {
        $imgUrl = $product->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->first()?->url;
    } catch (\Throwable) {
    }
    $price = null;
    try {
        $price = $product->price;
    } catch (\Throwable) {
    }
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-theme border border-slate-200 bg-white shadow-sm">
    <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}" class="relative block aspect-square overflow-hidden bg-slate-100">
        @if ($imgUrl)
            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-5xl text-slate-300">🛍️</div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="line-clamp-2 text-sm font-semibold">
            <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}" class="hover:text-primary">{{ $product->name }}</a>
        </h3>
        <p class="mt-auto text-base font-bold text-primary">
            {{ $price !== null ? number_format((float) $price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
        </p>
    </div>
</article>
