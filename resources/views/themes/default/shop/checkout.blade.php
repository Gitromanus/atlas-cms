@extends('layouts.shop')

@section('title', 'Оформление заказа')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Оформление заказа</h1>

    <div class="grid gap-8 lg:grid-cols-5">
        <form method="POST" action="{{ route('checkout.store') }}" class="lg:col-span-3">
            @csrf

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
                <h2 class="text-lg font-bold">Доставка и оплата</h2>

                <div>
                    <label class="mb-1 block text-sm font-medium">Способ доставки *</label>
                    <select name="delivery_method" required class="w-full rounded-theme border border-slate-300 px-4 py-2">
                        <option value="pickup" @selected(old('delivery_method') === 'pickup')>Самовывоз</option>
                        <option value="courier" @selected(old('delivery_method') === 'courier')>Курьер</option>
                        <option value="post" @selected(old('delivery_method') === 'post')>Почта России</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Адрес доставки</label>
                    <textarea name="delivery_address" rows="2"
                              class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">{{ old('delivery_address') }}</textarea>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Способ оплаты *</label>
                    <select name="payment_method" required class="w-full rounded-theme border border-slate-300 px-4 py-2">
                        <option value="cash" @selected(old('payment_method') === 'cash')>Наличными при получении</option>
                        <option value="card_online" @selected(old('payment_method') === 'card_online')>Банковской картой онлайн</option>
                        <option value="card_courier" @selected(old('payment_method') === 'card_courier')>Картой курьеру</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Комментарий к заказу</label>
                    <textarea name="comment" rows="3"
                              class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">{{ old('comment') }}</textarea>
                </div>
            </div>

            <button type="submit" class="mt-6 w-full rounded-theme bg-primary px-6 py-3.5 text-lg font-bold text-white hover:opacity-90">
                Подтвердить заказ
            </button>
        </form>

        <aside class="lg:col-span-2">
            <div class="sticky top-20 rounded-theme bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold">Ваш заказ</h2>

                <ul class="mb-4 space-y-3">
                    @foreach ($items as $item)
                        <li class="flex justify-between gap-3 text-sm">
                            <span>
                                {{ $item->product->name }} × {{ $item->quantity }}
                                @if (! empty($item->options))
                                    <span class="block text-xs text-slate-500">
                                        @foreach ($item->options as $key => $value)
                                            {{ $key }}: {{ $value }}@if (! $loop->last) · @endif
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                            <span class="shrink-0 font-medium">{{ number_format(($item->product->price ?? 0) * $item->quantity, 0, ',', ' ') }} ₽</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex justify-between border-t border-slate-200 pt-4 text-lg font-bold">
                    <span>Итого:</span>
                    <span>{{ number_format($total, 0, ',', ' ') }} ₽</span>
                </div>
            </div>
        </aside>
    </div>
@endsection