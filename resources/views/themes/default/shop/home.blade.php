@extends('layouts.shop')

@section('title', $currentTenant?->name ?? 'Магазин')

@section('content')
    <section class="relative mb-12 overflow-hidden rounded-theme bg-gradient-to-br from-primary via-primary/90 to-accent p-8 text-white shadow-lg shadow-primary/20 md:p-14">
        <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 right-24 h-72 w-72 rounded-full bg-white/5 blur-2xl"></div>

        <div class="relative max-w-2xl">
            <p class="mb-3 inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider backdrop-blur">
                {{ $currentTenant?->name ?? 'Интернет-магазин' }}
            </p>
            <h1 class="text-3xl font-extrabold leading-tight tracking-tight md:text-5xl">
                {{ $currentTenant?->slogan ?: 'Всё для вашего авто — с ценами и остатками из 1С' }}
            </h1>
            <p class="mt-4 max-w-xl text-white/80">
                Актуальные цены, реальные остатки и быстрая синхронизация с 1С. Найдите нужное в каталоге или через поиск.
            </p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('catalog.index') }}"
                   class="rounded-theme bg-white px-6 py-3 font-semibold text-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-100">
                    Перейти в каталог
                </a>
                @if ($categories->isNotEmpty())
                    <a href="{{ route('catalog.category', $categories->first()->slug ?: $categories->first()->id) }}"
                       class="rounded-theme border border-white/30 px-6 py-3 font-semibold text-white backdrop-blur transition hover:bg-white/10">
                        Популярные категории
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mb-12">
            <div class="mb-5 flex items-end justify-between gap-3">
                <h2 class="text-2xl font-bold tracking-tight">Категории</h2>
                <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-primary hover:underline">Все категории →</a>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a href="{{ route('catalog.category', $category->slug ?: $category->id) }}"
                       class="group rounded-theme border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-theme bg-primary/10 text-lg font-black text-primary transition group-hover:bg-primary group-hover:text-white">
                            {{ mb_strtoupper(mb_substr($category->name, 0, 1)) }}
                        </span>
                        <span class="mt-3 block font-semibold text-slate-800 group-hover:text-primary">{{ $category->name }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ $category->products_count_total ?? $category->products_count ?? 0 }} товаров</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <div class="mb-5 flex items-end justify-between gap-3">
            <h2 class="text-2xl font-bold tracking-tight">Новинки</h2>
            <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-primary hover:underline">Смотреть все →</a>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($products as $product)
                @include('shop.partials.product-card', ['product' => $product])
            @empty
                <p class="col-span-full rounded-theme border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                    Товары появятся после первого обмена с 1С.
                </p>
            @endforelse
        </div>
    </section>
@endsection