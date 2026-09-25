@extends('layouts.shop')
@section('title', $page->meta_title ?: $page->title)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="text-3xl font-extrabold">{{ $page->title }}</h1>
    <div class="prose mt-6 max-w-none">{!! nl2br(e($page->body)) !!}</div>
</div>
@endsection
