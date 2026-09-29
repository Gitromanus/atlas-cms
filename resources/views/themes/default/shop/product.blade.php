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
        $ratingCnt = $product->relationLoaded('approvedReviews') ? $product->approvedReviews->count() : 0;
        $ratingAvg = $ratingCnt ? round($product->approvedReviews->avg('rating'), 1) : null;

        $variantGroups = [];
        foreach ($product->features->where('is_variant', true) as $f) {
            $n = (string) $f->name;
            $v = (string) $f->value;
            if ($n === '' || $v === '') {
                continue;
            }
            $variantGroups[$n][$v] = true;
        }
        foreach ($product->variants ?? [] as $variant) {
            foreach ((array) ($variant->options ?? []) as $n => $v) {
                if (! filled($v)) {
                    continue;
                }
                $variantGroups[(string) $n][(string) $v] = true;
            }
        }
        $variantGroups = array_map(fn ($vals) => array_keys($vals), $variantGroups);
        $hasVariantPicker = $variantGroups !== [];

        $colorMap = [
            'белый' => '#ffffff', 'белая' => '#ffffff', 'white' => '#ffffff',
            'чёрный' => '#111111', 'черный' => '#111111', 'black' => '#111111',
            'серый' => '#9ca3af', 'gray' => '#9ca3af', 'grey' => '#9ca3af',
            'красный' => '#ef4444', 'red' => '#ef4444',
            'синий' => '#3b82f6', 'blue' => '#3b82f6',
            'голубой' => '#38bdf8',
            'зелёный' => '#22c55e', 'зеленый' => '#22c55e', 'green' => '#22c55e',
            'жёлтый' => '#eab308', 'желтый' => '#eab308', 'yellow' => '#eab308',
            'оранжевый' => '#f97316', 'orange' => '#f97316',
            'розовый' => '#ec4899', 'pink' => '#ec4899',
            'фиолетовый' => '#a855f7', 'purple' => '#a855f7',
            'коричневый' => '#92400e', 'brown' => '#92400e',
            'бежевый' => '#d6c3a8', 'beige' => '#d6c3a8',
            'бордовый' => '#9f1239',
        ];
        $resolveColor = function (string $value) use ($colorMap): ?string {
            $key = mb_strtolower(trim($value));
            if (isset($colorMap[$key])) {
                return $colorMap[$key];
            }
            foreach ($colorMap as $name => $hex) {
                if (str_contains($key, $name)) {
                    return $hex;
                }
            }

            return null;
        };
        $isColorName = function (string $name): bool {
            $n = mb_strtolower($name);

            return str_contains($n, 'цвет') || str_contains($n, 'color');
        };
        $isSizeName = function (string $name): bool {
            $n = mb_strtolower($name);

            return str_contains($n, 'размер') || str_contains($n, 'size');
        };

        $defaultSelection = [];
        foreach ($variantGroups as $name => $values) {
            $defaultSelection[$name] = $values[0] ?? null;
        }
    @endphp

    <nav class="mb-6 text-sm text-slate-500">
        <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
        @if ($product->category)
            <span class="mx-1">/</span>
            <a href="{{ route('catalog.category', ['categorySlug' => $product->category->slug ?: $product->category->id]) }}" class="hover:text-primary">{{ $product->category->name }}</a>
        @endif
        <span class="mx-1">/</span>
        <span class="text-slate-800">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-2">
        <div>
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                @if ($main?->url)
                    <img id="product-main-image" src="{{ $main->url }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
                @else
                    <div class="flex aspect-square items-center justify-center bg-slate-50 text-7xl text-slate-200">🛍️</div>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($gallery as $img)
                        @continue(! $img->url)
                        <button type="button" data-src="{{ $img->url }}" class="product-thumb h-20 w-20 shrink-0 overflow-hidden rounded-xl ring-2 ring-transparent transition hover:ring-primary focus:outline-none focus:ring-primary">
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
                @if ($ratingAvg)
                    <a href="#reviews" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700 ring-1 ring-amber-200">★ {{ $ratingAvg }} <span class="font-normal">({{ $ratingCnt }})</span></a>
                @endif
                @if ($stock !== null)
                    @if ($stock > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            В наличии · {{ rtrim(rtrim(number_format($stock, 2, ',', ' '), '0'), ',') }} {{ $product->unit ?: 'шт' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-600">Нет в наличии</span>
                    @endif
                @endif
            </div>

            @if ($product->description)
                <div class="mt-6 max-w-none text-slate-700"><div class="whitespace-pre-line leading-relaxed">{{ $product->description }}</div></div>
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

            @include('shop.partials.delivery-estimate', ['product' => $product])

            <div class="mt-8" x-data="productVariants(@json($defaultSelection), @json($variantGroups))" x-cloak>
                @if ($hasVariantPicker)
                    <div class="mb-6 space-y-4">
                        @foreach ($variantGroups as $optName => $values)
                            <div>
                                <p class="mb-2 text-sm font-semibold text-slate-700">
                                    {{ $optName }}:
                                    <span class="font-normal text-slate-500" x-text="selected['{{ $optName }}'] || ''"></span>
                                </p>
                                @if ($isColorName($optName))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($values as $val)
                                            @php $hex = $resolveColor($val); @endphp
                                            <button type="button"
                                                    @click="select('{{ $optName }}', @js($val))"
                                                    :class="selected['{{ $optName }}'] === @js($val) ? 'ring-2 ring-primary ring-offset-2 border-primary' : 'border-slate-200 hover:border-slate-300'"
                                                    class="h-9 w-9 rounded-lg border-2 transition"
                                                    title="{{ $val }}"
                                                    @if ($hex) style="background: {{ $hex }};" @endif>
                                                @if (! $hex)
                                                    <span class="text-[10px] font-bold">{{ mb_substr($val, 0, 2) }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif ($isSizeName($optName))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($values as $val)
                                            <button type="button"
                                                    @click="select('{{ $optName }}', @js($val))"
                                                    :class="selected['{{ $optName }}'] === @js($val) ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                                    class="min-w-[2.5rem] rounded-lg border px-3 py-2 text-sm font-semibold transition">
                                                {{ $val }}
                                            </button>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($values as $val)
                                            <button type="button"
                                                    @click="select('{{ $optName }}', @js($val))"
                                                    :class="selected['{{ $optName }}'] === @js($val) ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                                    class="rounded-lg border px-3 py-2 text-sm font-medium transition">
                                                {{ $val }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="options" :value="JSON.stringify(selected)">
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="number" name="quantity" min="1" max="999" value="1" class="w-24 rounded-xl border border-slate-300 py-3 text-center text-lg font-semibold focus:border-primary focus:outline-none">
                        <button type="submit" class="flex-1 rounded-xl bg-primary px-8 py-3.5 text-base font-bold text-white shadow-lg shadow-primary/25 hover:opacity-95 disabled:opacity-50 sm:flex-none" @if($stock !== null && $stock <= 0) disabled @endif>
                            {{ $stock !== null && $stock <= 0 ? 'Нет в наличии' : 'Добавить в корзину' }}
                        </button>
                    </div>
                </form>
            </div>
            <script>
                function productVariants(defaults, groups) {
                    return {
                        selected: { ...defaults },
                        groups: groups || {},
                        select(name, value) {
                            this.selected[name] = value;
                        },
                    };
                }
            </script>
        </div>
    </div>

    @php
        $enableReviews = (bool) ($currentTenant?->setting('enable_reviews', true));
        $reviews = $product->relationLoaded('approvedReviews') ? $product->approvedReviews : collect();
    @endphp

    @if ($enableReviews)
        <section class="mt-16 border-t border-slate-200 pt-12" id="reviews">
            <h2 class="mb-6 text-2xl font-bold tracking-tight">Отзывы</h2>
            <div class="grid gap-8 lg:grid-cols-5">
                <div class="space-y-4 lg:col-span-3">
                    @forelse ($reviews as $review)
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold">{{ $review->author_name }}</p>
                                <div class="text-sm text-amber-400">@for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $review->rating ? '' : 'text-slate-200' }}">★</span>@endfor</div>
                            </div>
                            @if ($review->body)<p class="mt-2 text-sm text-slate-700">{{ $review->body }}</p>@endif
                            <p class="mt-2 text-xs text-slate-400">{{ $review->created_at?->format('d.m.Y') }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Отзывов ещё нет</div>
                    @endforelse
                </div>
                <div class="lg:col-span-2">
                    <form method="POST" action="{{ route('product.review', ['productSlug' => $product->slug]) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        @csrf
                        <p class="mb-4 font-semibold">Оставить отзыв</p>
                        <div class="space-y-3">
                            <input type="text" name="author_name" required maxlength="120" placeholder="Имя" value="{{ old('author_name', auth('customers')->user()?->name) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <select name="rating" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option value="5">5 — отлично</option>
                                <option value="4">4 — хорошо</option>
                                <option value="3">3 — нормально</option>
                                <option value="2">2 — плохо</option>
                                <option value="1">1 — ужасно</option>
                            </select>
                            <textarea name="body" rows="3" maxlength="2000" placeholder="Комментарий" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                            <button type="submit" class="w-full rounded-xl bg-primary py-2.5 text-sm font-bold text-white hover:opacity-90">Отправить</button>
                            <p class="text-xs text-slate-400">Отзыв появится после модерации</p>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @endif

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
