<article class="group relative flex flex-col overflow-hidden rounded-theme border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/60">
    <a href="{{ route('product.show', $product->slug ?: $product->id) }}" class="relative block aspect-square overflow-hidden bg-slate-100">
        @if ($product->mainImage?->url)
            <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-5xl text-slate-300">🛍️</div>
        @endif

        <span class="absolute left-2 top-2 rounded-full px-2.5 py-1 text-[11px] font-semibold backdrop-blur
                     {{ $product->isAvailable() ? 'bg-green-500/90 text-white' : 'bg-slate-500/80 text-white' }}">
            {{ $product->isAvailable() ? 'В наличии' : 'Нет в наличии' }}
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

        @php
            // Только реальные комбинации из 1С: значения, которых нет в вариантах товара, не показываются.
            // Порядок значений — из админки (теги options), а не по алфавиту
            $cardCombos = $product->variants->filter(fn ($variant) => filled($variant->options));
            $cardVariantValues = $product->variantFeatures()
                ->filter(fn ($feature) => filled($feature->options))
                ->map(function ($feature) use ($cardCombos) {
                    $existing = $cardCombos->pluck('options.'.$feature->name)
                        ->filter()
                        ->unique()
                        ->values();

                    $values = collect($feature->options ?? [])
                        ->filter(fn ($value): bool => $existing->contains($value))
                        ->values();

                    if ($values->isEmpty()) {
                        $values = $existing;
                    }

                    return ['name' => $feature->name, 'values' => $values];
                })
                ->filter(fn ($item): bool => $item['values']->isNotEmpty())
                ->take(2);
        @endphp

        @if ($cardVariantValues->isNotEmpty())
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
                @foreach ($cardVariantValues as $item)
                    <span title="{{ $item['name'] }}: {{ $item['values']->implode(', ') }}">
                        {{ $item['name'] }}:
                        <span class="font-medium text-slate-700">{{ $item['values']->take(4)->implode(', ') }}</span>
                    </span>
                @endforeach
            </div>
        @endif

        <div class="mt-auto flex items-end justify-between gap-2 pt-3">
            <span class="text-lg font-extrabold tracking-tight text-slate-900">
                {{ $product->price !== null ? number_format($product->price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
            </span>

            @if ($product->hasVariants())
                <a href="{{ route('product.show', $product->slug ?: $product->id) }}"
                   class="rounded-theme bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90">
                    Выбрать вариант
                </a>
            @else
                <form method="POST" action="{{ route('cart.add') }}" class="inline">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit"
                            class="rounded-theme bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90">
                        В корзину
                    </button>
                </form>
            @endif
        </div>
    </div>
</article>