@extends('layouts.shop')

@section('title', $currentTenant?->name ?? 'Магазин')

@section('content')
    <section class="mb-10 rounded-theme bg-gradient-to-r from-primary to-accent p-8 text-white md:p-12">
        <h1 class="text-3xl font-extrabold md:text-4xl">{{ $currentTenant?->name ?? 'Добро пожаловать' }}</h1>
        <p class="mt-3 max-w-xl text-white/80">Товары из нашего магазина — всегда свежие цены и остатки, синхронизированные с 1С.</p>
        <a href="{{ route('catalog.index') }}" class="mt-6 inline-block rounded-theme bg-white px-6 py-3 font-semibold text-slate-900 hover:bg-slate-100">
            Перейти в каталог
        </a>
    </section>

    <h2 class="mb-4 text-2xl font-bold">Новинки</h2>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($products as $product)
            @include('shop.partials.product-card', ['product' => $product])
        @empty
            <p class="col-span-full text-slate-500">Товары появятся после первого обмена с 1С.</p>
        @endforelse
    </div>
@endsection