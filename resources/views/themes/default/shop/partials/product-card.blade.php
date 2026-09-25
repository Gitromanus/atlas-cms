@php
    $imgUrl = $product->mainImage?->url
        ?? $product->images->sortBy('sort_order')->first()?->url
        ?? null;
    $price = $product->price;
    $available = false;
    try {
        $available = $product->isAvailable();
    } catch (\Throwable) {
        $available = true;
    }
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-theme border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/60">
    <a href="{{ route('product.show', $product->slug ?: $product->id) }}" class="relative block aspect-square overflow-hidden bg-slate-100">
        @if ($imgUrl)
            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-5xl text-slate-300">🛍️</div>
        @endif

        <span class="absolute left-2 top-2 rounded-full px-2.5 py-1 text-[11px] font-semibold backdrop-blur
                     {{ $available ? 'bg-green-500/90 text-white' : 'bg-slate-500/80 text-white' }}">
            {{ $available ? 'В наличии' : 'Нет в наличии' }}
        </span>
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="line-clamp-2 min-h-10 text-sm font-semibold leading-5">
            <a href="{{ route('product.show', $product->slug ?: $product->id) }}" class="transition hover:text-primary">
                {{ $product->name }}
            </a>
        </h3>

        @if ($product->sku)
            <p class="text-xs text-slate-400">Артикул: {{ $product->sku }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-2 pt-1">
            <p class="text-base font-bold text-primary">
                {{ $price !== null ? number_format((float) $price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
            </p>
            <a href="{{ route('product.show', $product->slug ?: $product->id) }}"
               class="rounded-theme bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary">
                Смотреть
            </a>
        </div>
    </div>
</article>
