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

            @php
                // Реальные варианты из 1С: только существующие комбинации характеристик
                $variantCombos = $product->variants
                    ->filter(fn ($v) => filled($v->options))
                    ->map(fn ($v) => ['options' => $v->options, 'quantity' => (float) $v->quantity])
                    ->values();

                // Списки значений строятся из реальных комбинаций, а не из перекрёстного произведения
                $variantSelects = [];
                foreach ($product->variantFeatures() as $vf) {
                    $values = $variantCombos->pluck('options.'.$vf->name)->filter()->unique()->values();
                    if ($values->isNotEmpty()) {
                        $variantSelects[] = ['name' => $vf->name, 'options' => $values->all()];
                    }
                }

                $hasPicker = $variantSelects !== [] && $variantCombos->isNotEmpty();
            @endphp

            <form method="POST" action="{{ route('cart.add') }}" class="mt-8">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($hasPicker)
                    <div x-data="variantPicker(@js($variantSelects), @js($variantCombos))" class="mb-6 space-y-4">
                        <template x-for="(v, vi) in selects" :key="vi">
                            <div>
                                <span class="mb-1.5 block text-sm font-medium" x-text="v.name + ':'"></span>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="opt in v.options" :key="opt">
                                        <button type="button"
                                                @click="select(vi, opt)"
                                                :disabled="optionDisabled(vi, opt)"
                                                class="rounded-theme border px-4 py-1.5 text-sm transition disabled:cursor-not-allowed"
                                                :class="isSelected(vi, opt) ? 'border-primary bg-primary text-white' : (optionDisabled(vi, opt) ? 'border-slate-200 text-slate-300' : 'border-slate-300 hover:bg-slate-50')"
                                                x-text="opt"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p class="text-sm font-medium text-red-500" x-show="!isAvailable()" x-text="unavailableText()"></p>
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
                    <button type="submit"
                            @if ($hasPicker) :disabled="!isAvailable()" @endif
                            class="flex-1 rounded-theme bg-primary px-5 py-2 font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                        В корзину
                    </button>
                </div>
            </form>

            <script>
                function variantPicker(selects, combos) {
                    return {
                        selects,
                        combos,
                        selected: {},
                        init() {
                            this.selects.forEach((v, i) => { this.selected[i] = v.options[0]; });
                        },
                        select(index, value) {
                            this.selected[index] = value;
                        },
                        isSelected(index, value) {
                            return this.selected[index] === value;
                        },
                        trySelection(index, value) {
                            return { ...this.selected, [index]: value };
                        },
                        comboFor(sel) {
                            const map = {};
                            this.selects.forEach((v, i) => { map[v.name] = sel[i]; });
                            return this.combos.find(c => this.selects.every(v => c.options[v.name] === map[v.name])) || null;
                        },
                        optionDisabled(index, value) {
                            return this.comboFor(this.trySelection(index, value)) === null;
                        },
                        currentCombo() {
                            return this.comboFor(this.selected);
                        },
                        isAvailable() {
                            const c = this.currentCombo();
                            return c !== null && Number(c.quantity) > 0;
                        },
                        unavailableText() {
                            const c = this.currentCombo();
                            if (c === null) {
                                return 'Такого сочетания нет в наличии';
                            }
                            return 'Вариант закончился';
                        },
                        payload() {
                            const out = {};
                            this.selects.forEach((v, i) => { out[v.name] = this.selected[i]; });
                            return out;
                        },
                    };
                }
            </script>

            @php
                // Вариантные свойства (цвет, размер) показаны в пикере выше — не дублируем их в таблице характеристик
                $plainFeatures = $product->features->reject(fn ($feature) => $feature->is_variant);
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