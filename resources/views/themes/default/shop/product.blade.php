@extends('layouts.shop')

@section('title', $product->name)

@section('content')
    <div class="grid gap-8 md:grid-cols-2">
        <div class="overflow-hidden rounded-theme bg-white shadow-sm">
            @if ($product->images->isNotEmpty())
                <div x-data="{ active: 0, images: {{ $product->images->pluck('url')->map(fn ($u) => $u)->toJson() }} }">
                    <img :src="images[active]" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
                    <div class="flex gap-2 p-3">
                        @foreach ($product->images as $index => $image)
                            <img src="{{ $image->url }}" alt="" @click="active = {{ $index }}"
                                 class="h-16 w-16 cursor-pointer rounded-theme object-cover"
                                 :class="active === {{ $index }} ? 'ring-2 ring-primary' : ''">
                        @endforeach
                    </div>
                </div>
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
                {{ $product->price !== null ? number_format($product->price, 0, ',', ' ') . ' ₽' : 'Цена по запросу' }}
            </p>

            <p class="mt-2 text-sm {{ $product->isAvailable() ? 'text-green-600' : 'text-red-500' }}">
                {{ $product->isAvailable() ? 'В наличии' : 'Нет в наличии' }}
            </p>

            @if ($product->description)
                <div class="mt-6 whitespace-pre-line text-slate-700">{!! nl2br(e($product->description)) !!}</div>
            @endif

            @php $variants = $product->variantFeatures(); @endphp

            <form method="POST" action="{{ route('cart.add') }}" class="mt-8">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($variants->isNotEmpty())
                    <div x-data="variantPicker(@js($variants->map(fn ($f) => [
                        'name' => $f->name,
                        'options' => $f->options ?: [$f->value],
                        'default' => $f->value,
                    ])->values()))" class="mb-6 space-y-4">
                        <template x-for="(v, vi) in variants" :key="vi">
                            <div>
                                <span class="mb-1.5 block text-sm font-medium" x-text="v.name + ':'"></span>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="opt in v.options" :key="opt">
                                        <button type="button"
                                                @click="select(vi, opt)"
                                                class="rounded-theme border px-4 py-1.5 text-sm transition"
                                                :class="isSelected(vi, opt) ? 'border-primary bg-primary text-white' : 'border-slate-300 hover:bg-slate-50'"
                                                x-text="opt"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <input type="hidden" name="options" :value="JSON.stringify(payload())">
                    </div>
                @endif

                <div class="flex max-w-md items-center gap-3" x-data="{ qty: 1 }">
                    <div class="flex items-center overflow-hidden rounded-theme border border-slate-300 bg-white">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" class="px-3.5 py-2 text-lg leading-none text-slate-500 hover:bg-slate-100" aria-label="Уменьшить количество">−</button>
                        <input type="number" name="quantity" x-model.number="qty" min="1" max="999" value="1"
                               class="w-16 border-x border-slate-200 py-2 text-center focus:outline-none">
                        <button type="button" @click="qty = Math.min(999, qty + 1)" class="px-3.5 py-2 text-lg leading-none text-slate-500 hover:bg-slate-100" aria-label="Увеличить количество">+</button>
                    </div>
                    <button type="submit" class="flex-1 rounded-theme bg-primary px-5 py-2 font-semibold text-white hover:opacity-90">
                        В корзину
                    </button>
                </div>
            </form>

            <script>
                function variantPicker(variants) {
                    return {
                        variants,
                        selected: {},
                        init() {
                            this.variants.forEach((v, i) => { this.selected[i] = v.default; });
                        },
                        select(index, value) {
                            this.selected[index] = value;
                        },
                        isSelected(index, value) {
                            return this.selected[index] === value;
                        },
                        payload() {
                            const out = {};
                            this.variants.forEach((v, i) => { out[v.name] = this.selected[i]; });
                            return out;
                        },
                    };
                }
            </script>

            @if ($product->features->isNotEmpty())
                <h2 class="mt-10 mb-3 text-xl font-bold">Характеристики</h2>
                <dl class="divide-y divide-slate-200 rounded-theme bg-white shadow-sm">
                    @foreach ($product->features as $feature)
                        <div class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="text-slate-500">{{ $feature->name }}</dt>
                            <dd class="font-medium">{{ $feature->value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
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