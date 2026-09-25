@extends('layouts.shop')
@section('title', 'Сравнение')
@section('content')
<div class="mx-auto max-w-6xl px-4 py-10">
    <h1 class="text-2xl font-extrabold">Сравнение товаров</h1>
    @if($products->isEmpty())
        <p class="mt-6 text-slate-500">Добавьте товары кнопкой ⇄</p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <tr><th class="p-2 text-left">Товар</th>
                @foreach($products as $p)<th class="p-2 text-left"><a href="{{ route('product.show',$p->slug) }}" class="text-primary font-semibold">{{ $p->name }}</a>
                <div>{{ number_format((float)($p->price??0),0,',',' ') }} ₽</div></th>@endforeach</tr>
                @foreach($featureNames as $fn)
                <tr><td class="p-2 text-slate-500">{{ $fn }}</td>
                @foreach($products as $p)<td class="p-2">{{ $p->features->where('name',$fn)->pluck('value')->filter()->implode(', ') ?: '—' }}</td>@endforeach</tr>
                @endforeach
            </table>
        </div>
        <form method="POST" action="{{ route('compare.clear') }}" class="mt-4">@csrf<button class="text-sm text-red-600">Очистить</button></form>
    @endif
</div>
@endsection
