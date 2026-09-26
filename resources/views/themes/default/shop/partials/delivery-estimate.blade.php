{{-- Оценка доставки на карточке товара --}}
<script>
window.deliveryEstimate = window.deliveryEstimate || function (cfg) {
    return {
        url: cfg.url,
        productId: cfg.productId,
        city: cfg.initialCity || '',
        cityInput: cfg.initialCity || '',
        editing: false,
        loading: true,
        error: null,
        options: [],
        minPrice: null,
        formatPrice(n) {
            return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(n);
        },
        async init() { await this.load(); },
        async applyCity() {
            this.city = (this.cityInput || '').trim();
            this.editing = false;
            await this.load();
        },
        async load() {
            this.loading = true;
            this.error = null;
            try {
                const params = new URLSearchParams();
                params.set('product_id', String(this.productId));
                if (this.city) params.set('city', this.city);
                const res = await fetch(this.url + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!data.ok) {
                    this.error = data.message || 'Не удалось рассчитать';
                    this.options = [];
                    this.minPrice = null;
                } else {
                    this.city = data.city || this.city;
                    this.cityInput = this.city;
                    this.options = data.options || [];
                    this.minPrice = data.min_price;
                    if (!this.options.length) {
                        this.error = data.message || 'Нет вариантов для этого города';
                    }
                }
            } catch (e) {
                this.error = 'Ошибка сети';
                this.options = [];
                this.minPrice = null;
            } finally {
                this.loading = false;
            }
        }
    };
};
</script>
<div
    x-data="deliveryEstimate({
        url: @js(route('delivery.estimate')),
        productId: {{ (int) $product->id }},
        initialCity: @js(session('visitor_city', ''))
    })"
    x-init="init()"
    class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/80 p-4"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-slate-800">Доставка</div>
            <div class="mt-0.5 text-xs text-slate-500" x-show="!loading && city">
                в <span class="font-medium text-slate-700" x-text="city"></span>
                <button type="button" class="ml-1 text-primary underline-offset-2 hover:underline" @click="editing = true" x-show="!editing">изменить</button>
            </div>
        </div>
        <div class="text-right text-sm font-bold text-slate-900" x-show="minPrice !== null && !loading">
            от <span x-text="formatPrice(minPrice)"></span>&nbsp;₽
        </div>
    </div>

    <div class="mt-3 flex gap-2" x-show="editing" x-cloak>
        <input type="text" x-model="cityInput" @keydown.enter.prevent="applyCity()"
               placeholder="Город" class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-primary focus:outline-none">
        <button type="button" @click="applyCity()" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white">OK</button>
    </div>

    <div class="mt-3 text-sm text-slate-500" x-show="loading">Считаем доставку…</div>
    <div class="mt-3 text-sm text-amber-700" x-show="!loading && error" x-text="error"></div>

    <ul class="mt-3 space-y-1.5" x-show="!loading && options.length">
        <template x-for="(opt, i) in options" :key="i">
            <li class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-slate-100">
                <span class="text-slate-700" x-text="opt.label || opt.type"></span>
                <span class="shrink-0 font-semibold text-slate-900">
                    <span x-text="formatPrice(opt.price)"></span>&nbsp;₽
                </span>
            </li>
        </template>
    </ul>
</div>
