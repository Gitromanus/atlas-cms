@extends('layouts.shop')

@section('title', $currentTenant?->name ?? 'Магазин')

@section('content')
@php
    $tenant = $currentTenant ?? null;
    $about = $tenant?->setting('about');
    $phone = $tenant?->setting('phone');
@endphp

<section class="relative mb-10 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-primary px-6 py-12 text-white shadow-xl md:px-12 md:py-16">
    <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-primary/40 blur-3xl"></div>
    <div class="relative grid items-center gap-8 lg:grid-cols-2">
        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Интернет-магазин</p>
            <h1 class="text-3xl font-black leading-tight tracking-tight md:text-5xl">{{ $tenant?->name ?? 'Магазин' }}</h1>
            <p class="mt-4 max-w-lg text-base text-white/80 md:text-lg">{{ $about ?: 'Актуальные цены, остатки на складе и удобный заказ онлайн.' }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('catalog.index') }}" class="rounded-2xl bg-white px-6 py-3.5 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-slate-100">Смотреть каталог</a>
                @if ($phone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="rounded-2xl border border-white/30 bg-white/10 px-6 py-3.5 text-sm font-semibold backdrop-blur transition hover:bg-white/20">{{ $phone }}</a>
                @endif
            </div>
        </div>
        <div class="hidden lg:grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white/10 p-5 backdrop-blur">
                <p class="text-3xl font-black">{{ $products->count() }}+</p>
                <p class="mt-1 text-sm text-white/70">товаров в витрине</p>
            </div>
            <div class="rounded-2xl bg-white/10 p-5 backdrop-blur">
                <p class="text-3xl font-black">{{ $categories->count() }}</p>
                <p class="mt-1 text-sm text-white/70">категорий</p>
            </div>
            <div class="col-span-2 rounded-2xl bg-white/10 p-5 backdrop-blur">
                <p class="text-sm font-semibold">Быстрый поиск</p>
                <form action="{{ route('catalog.index') }}" method="GET" class="mt-2 flex gap-2">
                    <input type="search" name="q" placeholder="Найти товар…" class="w-full rounded-xl border-0 bg-white/90 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white">
                    <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold">Найти</button>
                </form>
            </div>
        </div>
    </div>
</section>

@if ($categories->isNotEmpty())
<section class="mb-12">
    <div class="mb-5 flex items-end justify-between gap-3">
        <h2 class="text-xl font-bold tracking-tight md:text-2xl">Категории</h2>
        <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-primary hover:underline">Все товары →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        @foreach ($categories->take(12) as $cat)
            <a href="{{ route('catalog.category', ['categorySlug' => $cat->slug ?: $cat->id]) }}" class="group flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary transition group-hover:bg-primary group-hover:text-white">{{ mb_substr($cat->name, 0, 1) }}</span>
                <span class="line-clamp-2 text-xs font-semibold text-slate-800">{{ $cat->name }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="mb-14">
    <div class="mb-5 flex items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold tracking-tight md:text-2xl">Популярные товары</h2>
            <p class="mt-1 text-sm text-slate-500">Актуальные позиции с остатками на складе</p>
        </div>
        <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-primary hover:underline">Каталог →</a>
    </div>
    @if ($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-slate-500">Товары появятся после добавления в админке</div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                @include('shop.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    @endif
</section>

@if (($enableNews && $news->isNotEmpty()) || ($enableArticles && $articles->isNotEmpty()))
<div class="mb-14 grid gap-10 lg:grid-cols-5">
    @if ($enableNews && $news->isNotEmpty())
        <section class="lg:col-span-2">
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-xl font-bold">Новости</h2>
                <a href="{{ route('news.index') }}" class="text-sm text-primary hover:underline">Все</a>
            </div>
            <div class="space-y-3">
                @foreach ($news as $item)
                    <a href="{{ route('posts.show', ['postSlug' => $item->slug]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary/30 hover:shadow-md">
                        <p class="text-xs font-medium text-slate-400">{{ $item->published_at?->format('d.m.Y') }}</p>
                        <p class="mt-1 font-semibold text-slate-900 line-clamp-2">{{ $item->title }}</p>
                        @if ($item->excerpt)<p class="mt-1 text-sm text-slate-500 line-clamp-2">{{ $item->excerpt }}</p>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    @if ($enableArticles && $articles->isNotEmpty())
        <section class="{{ ($enableNews && $news->isNotEmpty()) ? 'lg:col-span-3' : 'lg:col-span-5' }}">
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-xl font-bold">Статьи</h2>
                <a href="{{ route('articles.index') }}" class="text-sm text-primary hover:underline">Все</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $item)
                    <a href="{{ route('posts.show', ['postSlug' => $item->slug]) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="aspect-[16/10] overflow-hidden bg-slate-100">
                            @if ($item->cover_url)
                                <img src="{{ $item->cover_url }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full items-center justify-center text-3xl text-slate-300">📄</div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-xs text-slate-400">{{ $item->published_at?->format('d.m.Y') }}</p>
                            <p class="mt-1 font-semibold line-clamp-2">{{ $item->title }}</p>
                            @if ($item->excerpt)<p class="mt-1 text-sm text-slate-500 line-clamp-2">{{ $item->excerpt }}</p>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endif

@if ($enableReviews && $latestReviews->isNotEmpty())
<section class="mb-8">
    <div class="mb-5 flex items-end justify-between">
        <h2 class="text-xl font-bold tracking-tight md:text-2xl">Отзывы покупателей</h2>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($latestReviews as $review)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-1 text-amber-400">
                    @for ($i = 1; $i <= 5; $i++)
                        <span class="{{ $i <= $review->rating ? '' : 'text-slate-200' }}">★</span>
                    @endfor
                </div>
                <p class="mt-3 text-sm leading-relaxed text-slate-700 line-clamp-4">{{ $review->body ?: 'Без комментария' }}</p>
                <div class="mt-4 flex items-center justify-between gap-2 text-xs text-slate-500">
                    <span class="font-semibold text-slate-800">{{ $review->author_name }}</span>
                    @if ($review->product)
                        <a href="{{ route('product.show', ['productSlug' => $review->product->slug]) }}" class="truncate text-primary hover:underline">{{ $review->product->name }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
@endsection
