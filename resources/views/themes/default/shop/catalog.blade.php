@extends('layouts.shop')

@section('title', $category?->name ?? 'Каталог')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">{{ $category?->name ?? 'Каталог товаров' }}</h1>

    @if ($categories->isNotEmpty())
        <nav class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('catalog.index') }}"
               class="rounded-theme px-4 py-2 text-sm @if (! $category) bg-primary text-white @else bg-white text-slate-700 hover:bg-slate-100 @endif">
                Все товары
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('catalog.category', $cat->slug ?: $cat->id) }}"
                   class="rounded-theme px-4 py-2 text-sm @if ($category?->id === $cat->id) bg-primary text-white @else bg-white text-slate-700 hover:bg-slate-100 @endif">
                    {{ $cat->name }} ({{ $cat->products_count_total ?? $cat->products_count ?? 0 }})
                </a>
            @endforeach
        </nav>
    @endif

    @if ($categoryNode !== null && filled($categoryNode->children ?? null) && $categoryNode->children->isNotEmpty())
        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($categoryNode->children as $child)
                <a href="{{ route('catalog.category', $child->slug ?: $child->id) }}"
                   class="rounded-theme border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-primary hover:bg-slate-50">
                    <span class="font-medium">{{ $child->name }}</span>
                    <span class="block text-xs text-slate-400">{{ $child->products_count_total ?? $child->products_count ?? 0 }} тов.</span>
                </a>
            @endforeach
        </div>
    @endif

    <form method="GET" class="mb-8 flex max-w-md gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Поиск по каталогу..."
               class="w-full rounded-theme border border-slate-300 px-4 py-2 focus:border-primary focus:outline-none">
        <button type="submit" class="rounded-theme bg-primary px-5 py-2 font-medium text-white hover:opacity-90">Найти</button>
    </form>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($products as $product)
            @include('shop.partials.product-card', ['product' => $product])
        @empty
            <p class="col-span-full text-slate-500">Ничего не найдено.</p>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $products->links() }}
    </div>
@endsection