@extends('layouts.shop')

@section('title', $category?->name ?? 'Каталог')

@section('content')
    <nav class="mb-2 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-primary">Главная</a>
        @if ($category)
            → <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
        @endif
    </nav>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ $category?->name ?? 'Каталог товаров' }}</h1>
        @if ($products->total() > 0)
            <p class="text-sm text-slate-500">
                Найдено: <span class="font-semibold text-slate-700">{{ $products->total() }}</span>
            </p>
        @endif
    </div>

    @if ($categories->isNotEmpty())
        <nav class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('catalog.index') }}"
               class="rounded-full px-4 py-1.5 text-sm font-medium transition @if (! $category) bg-primary text-white shadow-sm @else bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 @endif">
                Все товары
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('catalog.category', $cat->slug ?: $cat->id) }}"
                   class="rounded-full px-4 py-1.5 text-sm font-medium transition @if ($category?->id === $cat->id) bg-primary text-white shadow-sm @else bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 @endif">
                    {{ $cat->name }}
                    <span class="opacity-60">({{ $cat->products_count_total ?? $cat->products_count ?? 0 }})</span>
                </a>
            @endforeach
        </nav>
    @endif

    @if ($categoryNode !== null && filled($categoryNode->children ?? null) && $categoryNode->children->isNotEmpty())
        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($categoryNode->children as $child)
                <a href="{{ route('catalog.category', $child->slug ?: $child->id) }}"
                   class="group rounded-theme border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                    <span class="font-medium group-hover:text-primary">{{ $child->name }}</span>
                    <span class="block text-xs text-slate-400">{{ $child->products_count_total ?? $child->products_count ?? 0 }} тов.</span>
                </a>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ url()->current() }}">
        <div class="grid items-start gap-6 lg:grid-cols-[250px_1fr]">
            @if ($filterOptions !== [])
                <aside class="space-y-5 rounded-theme border border-slate-200 bg-white p-5 lg:sticky lg:top-20">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold">Фильтры</h2>
                        <a href="{{ url()->current() }}" class="text-xs text-slate-400 transition hover:text-primary">Сбросить</a>
                    </div>

                    @foreach ($filterOptions as $name => $option)
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700">{{ $name }}</p>
                            <div class="space-y-1.5">
                                @foreach ($option['values'] as $value)
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 transition hover:text-slate-900">
                                        <input type="checkbox"
                                               name="f[{{ $name }}][]"
                                               value="{{ $value }}"
                                               @checked(in_array($value, $selectedFilters[$name] ?? [], true))
                                               class="h-4 w-4 rounded border-slate-300 accent-[var(--color-primary)]">
                                        {{ $value }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <button type="submit"
                            class="w-full rounded-theme bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                        Применить
                    </button>
                </aside>
            @endif

            <div>
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full max-w-sm">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Поиск по каталогу..."
                               class="w-full rounded-theme border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        Сортировка:
                        <select name="sort" onchange="this.form.submit()"
                                class="rounded-theme border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            <option value="new" @selected($sort === 'new' || $sort === '')>Сначала новинки</option>
                            <option value="popular" @selected($sort === 'popular')>По популярности</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Сначала дешевле</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Сначала дороже</option>
                            <option value="name_asc" @selected($sort === 'name_asc')>По алфавиту (А–Я)</option>
                            <option value="name_desc" @selected($sort === 'name_desc')>По алфавиту (Я–А)</option>
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($products as $product)
                        @include('shop.partials.product-card', ['product' => $product])
                    @empty
                        <div class="col-span-full rounded-theme border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                            Ничего не найдено. Попробуйте изменить запрос или сбросить фильтры.
                        </div>
                    @endforelse
                </div>

                @if ($products->hasPages())
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </form>
@endsection