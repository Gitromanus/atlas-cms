@extends('layouts.shop')

@section('title', 'Оформление заказа')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Оформление заказа</h1>

    <div class="grid gap-8 lg:grid-cols-5" x-data="checkoutDelivery()">
        <form method="POST" action="{{ route('checkout.store') }}" class="lg:col-span-3" @submit="beforeSubmit">
            @csrf
            <input type="hidden" name="delivery_cost" :value="deliveryPrice ?? ''">

            <div class="space-y-4 rounded-theme bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">Контактные данные</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium">Имя *</label>
                    <input type="text" name="name" value="{{ old('name', auth('customers')->user()?->name) }}" required
                           class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Телефон *</label>
                        <input type="tel" name="phone" value="{{ old('phone', auth('customers')->user()?->phone) }}" required
                               class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input type="email" name="email" value="{{ old('email', auth('customers')->user()?->email) }}"
                               class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="mt-6 space-y-4 rounded-theme bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">Доставка</h2>
                @if ($deliveryMethods->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($deliveryMethods as $method)
                            @php $isYandex = in_array(strtolower((string) $method->code), ['yandex', 'yandex_delivery', 'yandex-delivery'], true); @endphp
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-primary/40">
                                <input type="radio" name="delivery_method_id" value="{{ $method->id }}" class="mt-1"
                                       data-yandex="{{ $isYandex ? '1' : '0' }}"
                                       data-price="{{ $method->costFor($total) }}"
                                       data-require-address="{{ $method->require_address ? '1' : '0' }}"
                                       x-model="methodId" @change="onMethodChange"
                                       @checked(old('delivery_method_id') == $method->id || ($loop->first && ! old('delivery_method_id')))>
                                <span class="flex-1">
                                    <span class="font-medium">{{ $method->name }}</span>
                                    @if ($method->description)<span class="mt-0.5 block text-xs text-slate-500">{{ $method->description }}</span>@endif
                                    @if ($isYandex)<span class="mt-0.5 block text-xs text-sky-600">Расчёт через Яндекс Доставку</span>
                                    @elseif ((float) $method->costFor($total) <= 0)<span class="mt-0.5 block text-xs text-emerald-600">Бесплатно</span>
                                    @else<span class="mt-0.5 block text-xs text-slate-600">{{ number_format($method->costFor($total), 0, ',', ' ') }} ₽</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div>
                        <label class="mb-1 block text-sm font-medium">Способ доставки *</label>
                        <select name="delivery_method" x-model="legacyMethod" @change="onMethodChange" class="w-full rounded-theme border border-slate-300 px-4 py-2">
                            <option value="pickup">Самовывоз — 0 ₽</option>
                            <option value="courier">Курьер — 300 ₽</option>
                            <option value="post">Почта России — 350 ₽</option>
                            @if (!empty($yandexDeliveryEnabled))<option value="yandex">Яндекс Доставка (расчёт)</option>@endif
                        </select>
                    </div>
                @endif

                <div x-show="needAddress" x-cloak>
                    <label class="mb-1 block text-sm font-medium">Адрес доставки *</label>
                    <textarea name="delivery_address" rows="2" x-model="address" @blur="recalc"
                              placeholder="Город, улица, дом, квартира"
                              class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">{{ old('delivery_address') }}</textarea>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm" x-show="statusMessage" x-text="statusMessage"
                     :class="calcError ? 'text-amber-700' : 'text-slate-600'"></div>
            </div>

            <div class="mt-6 space-y-4 rounded-theme bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold">Оплата</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium">Способ оплаты *</label>
                    <select name="payment_method" required class="w-full rounded-theme border border-slate-300 px-4 py-2">
                        <option value="cash">Наличными при получении</option>
                        @if (!empty($yookassaEnabled))<option value="card_online">Банковской картой онлайн</option>@endif
                        <option value="card_courier">Картой курьеру</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Комментарий</label>
                    <textarea name="comment" rows="3" class="w-full rounded-theme border border-slate-300 px-4 py-2">{{ old('comment') }}</textarea>
                </div>
            </div>

            <button type="submit" class="mt-6 w-full rounded-theme bg-primary px-6 py-3.5 text-lg font-bold text-white hover:opacity-90" :disabled="calculating">
                Подтвердить заказ
            </button>
        </form>

        <aside class="lg:col-span-2">
            <div class="sticky top-20 rounded-theme bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold">Ваш заказ</h2>
                <ul class="mb-4 space-y-3">
                    @foreach ($items as $item)
                        <li class="flex justify-between gap-3 text-sm">
                            <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span class="shrink-0 font-medium">{{ number_format(($item->product->price ?? 0) * $item->quantity, 0, ',', ' ') }} ₽</span>
                        </li>
                    @endforeach
                </ul>
                <div class="space-y-2 border-t border-slate-200 pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Товары</span><span>{{ number_format($total, 0, ',', ' ') }} ₽</span></div>
                    <div class="flex justify-between"><span class="text-slate-600">Доставка</span><span x-text="deliveryLabel">—</span></div>
                </div>
                <div class="mt-3 flex justify-between border-t border-slate-200 pt-4 text-lg font-bold">
                    <span>Итого:</span><span x-text="grandTotalLabel">{{ number_format($total, 0, ',', ' ') }} ₽</span>
                </div>
            </div>
        </aside>
    </div>

    <script>
        function checkoutDelivery() {
            const itemsTotal = {{ (float) $total }};
            const calcUrl = @json(route('delivery.calculate'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;
            return {
                methodId: @json(old('delivery_method_id', $deliveryMethods->first()?->id)),
                legacyMethod: @json(old('delivery_method', 'pickup')),
                address: @json(old('delivery_address', '')),
                deliveryPrice: null, calculating: false, calcError: false, statusMessage: '', isYandex: false, needAddress: true,
                init() { this.onMethodChange(); },
                selectedRadio() { return document.querySelector('input[name="delivery_method_id"]:checked'); },
                onMethodChange() {
                    const radio = this.selectedRadio();
                    if (radio) {
                        this.isYandex = radio.dataset.yandex === '1';
                        this.needAddress = radio.dataset.requireAddress === '1' || this.isYandex;
                        if (!this.isYandex) { this.deliveryPrice = parseFloat(radio.dataset.price || '0'); this.statusMessage = ''; this.calcError = false; }
                        else { this.deliveryPrice = null; this.recalc(); }
                    } else {
                        this.isYandex = this.legacyMethod === 'yandex';
                        this.needAddress = this.legacyMethod !== 'pickup';
                        if (this.legacyMethod === 'pickup') this.deliveryPrice = 0;
                        else if (this.legacyMethod === 'courier') this.deliveryPrice = 300;
                        else if (this.legacyMethod === 'post') this.deliveryPrice = 350;
                        else this.recalc();
                    }
                },
                async recalc() {
                    if (this.isYandex && !(this.address || '').trim()) { this.statusMessage = 'Введите адрес для расчёта'; this.deliveryPrice = null; return; }
                    this.calculating = true; this.statusMessage = 'Считаем…'; this.calcError = false;
                    try {
                        const body = new URLSearchParams();
                        body.set('_token', csrf);
                        if (this.methodId) body.set('delivery_method_id', this.methodId);
                        if (this.legacyMethod) body.set('delivery_method', this.legacyMethod);
                        body.set('address', this.address || '');
                        const res = await fetch(calcUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body });
                        const data = await res.json();
                        if (data.price !== null && data.price !== undefined) this.deliveryPrice = parseFloat(data.price);
                        this.statusMessage = data.message || ''; this.calcError = !data.ok;
                    } catch (e) { this.statusMessage = 'Ошибка сети'; this.calcError = true; }
                    finally { this.calculating = false; }
                },
                get deliveryLabel() {
                    if (this.deliveryPrice === null) return '—';
                    if (this.deliveryPrice <= 0) return 'Бесплатно';
                    return new Intl.NumberFormat('ru-RU').format(Math.round(this.deliveryPrice)) + ' ₽';
                },
                get grandTotalLabel() {
                    const d = this.deliveryPrice === null ? 0 : this.deliveryPrice;
                    return new Intl.NumberFormat('ru-RU').format(Math.round(itemsTotal + d)) + ' ₽';
                },
                beforeSubmit(e) {
                    if (this.isYandex && !(this.address || '').trim()) { e.preventDefault(); this.statusMessage = 'Укажите адрес'; this.calcError = true; }
                },
            };
        }
    </script>
@endsection
