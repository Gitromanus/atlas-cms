@extends('layouts.shop')
@section('title', 'Избранное')
@section('content')
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1 class="text-2xl font-extrabold">Избранное</h1>
    @if($products->isEmpty())
        <p class="mt-6 text-slate-500">Список пуст.</p>
    @else
        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($products as $product) @include('shop.partials.product-card', ['product'=>$product]) @endforeach
        </div>
    @endif
</div>
@endsection
