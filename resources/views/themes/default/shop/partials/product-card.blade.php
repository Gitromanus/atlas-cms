<article class="group flex flex-col overflow-hidden rounded-theme bg-white shadow-sm transition hover:shadow-md">
    <a href="{{ route('product.show', $product->slug ?: $product->id) }}" class="block aspect-square overflow-hidden bg-slate-100">
        @if ($product->mainImage?->url)
            <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-4xl text-slate-300">🛍️</div>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="line-clamp-2 text-sm font-semibold">
            <a href="{{ route('product.show', $product->slug ?: $product->id) }}" class="hover:text-primary">
                {{ $product->name }}
            </a>
        </h3>

        @if ($product->sku)
            <p class="text-xs text-slate-400">Артикул: {{ $product->sku }}</p>
        @endif

        @php
            // Только реальные комбинации из 1С: значения, которых нет в вариантах товара, не показываются
            $cardCombos = $product->variants->filter(fn ($variant) => filled($variant->options));
            $cardVariantValues = $product->variantFeatures()
                ->filter(fn ($feature) => filled($feature->options))
                ->map(function ($feature) use ($cardCombos) {
                    $values = $cardCombos->pluck('options.'.$feature->name)
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

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

        <div class="mt-auto flex items-center justify-between pt-2">
            <span class="text-lg font-bold">
                {{ $product->price !== null ? number_format($product->price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
            </span>
            <span class="text-xs {{ $product->isAvailable() ? 'text-green-600' : 'text-slate-400' }}">
                {{ $product->isAvailable() ? 'В наличии' : 'Нет в наличии' }}
            </span>
        </div>
    </div>
</article>