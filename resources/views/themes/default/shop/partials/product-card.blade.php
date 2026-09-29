@php
    $images = collect();
    try {
        $images = $product->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
    } catch (\Throwable) {}
    $imgUrl = $images->first()?->url;
    $imgUrl2 = $images->count() > 1 ? $images->get(1)?->url : null;
    $price = null;
    try { $price = $product->price; } catch (\Throwable) {}
    $stock = null;
    try {
        if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            $stock = (float) $product->variants->sum('quantity');
        } elseif ($product->relationLoaded('stocks')) {
            $stock = (float) $product->stocks->sum('quantity');
        } else {
            $stock = $product->stockTotal();
        }
    } catch (\Throwable) { $stock = null; }
    $featureChips = collect();
    try {
        $featureChips = $product->relationLoaded('features')
            ? $product->features->where('is_variant', false)->take(3)
            : collect();
    } catch (\Throwable) {}
@endphp
<article class="group relative flex w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}"
       class="relative block aspect-[4/5] w-full overflow-hidden bg-slate-50 sm:aspect-square">
        @if ($imgUrl)
            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" loading="lazy"
                 class="absolute inset-0 h-full w-full object-contain p-2 transition duration-500 ease-out {{ $imgUrl2 ? 'group-hover:opacity-0' : 'group-hover:scale-105' }}">
            @if ($imgUrl2)
                <img src="{{ $imgUrl2 }}" alt="" loading="lazy" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-contain p-2 opacity-0 transition duration-500 ease-out group-hover:opacity-100 group-hover:scale-105">
            @endif
        @else
            <div class="flex h-full w-full items-center justify-center text-5xl text-slate-300">🛍️</div>
        @endif
        @if ($stock !== null)
            @if ($stock > 0)
                <span class="absolute left-2 top-2 z-10 rounded-full bg-emerald-500/95 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow">
                    В наличии
                </span>
            @else
                <span class="absolute left-2 top-2 z-10 rounded-full bg-slate-700/90 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow">
                    Нет в наличии
                </span>
            @endif
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-3 sm:p-4">
        <h3 class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-slate-900">
            <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}" class="hover:text-primary">
                {{ $product->name }}
            </a>
        </h3>
        @if ($product->sku)
            <p class="text-xs text-slate-400">Арт. {{ $product->sku }}</p>
        @endif
        @if ($featureChips->isNotEmpty())
            <div class="flex flex-wrap gap-1">
                @foreach ($featureChips as $chip)
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">{{ $chip->name }}: {{ $chip->value }}</span>
                @endforeach
            </div>
        @endif
        <div class="mt-auto flex items-end justify-between gap-2 pt-1">
            <div>
                @if ($price !== null)
                    <p class="text-lg font-bold text-slate-900">{{ number_format((float) $price, 0, '.', ' ') }} ₽</p>
                @else
                    <p class="text-sm text-slate-400">Цена по запросу</p>
                @endif
            </div>
            <form action="{{ route('cart.add') }}" method="POST" class="shrink-0" onclick="event.stopPropagation()">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" @disabled($stock !== null && $stock <= 0)
                        class="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                    В корзину
                </button>
            </form>
        </div>
    </div>
</article>
