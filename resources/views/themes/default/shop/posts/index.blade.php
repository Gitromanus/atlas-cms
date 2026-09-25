@extends('layouts.shop')

@section('title', $heading)

@section('content')
    <h1 class="mb-2 text-3xl font-bold tracking-tight">{{ $heading }}</h1>
    <p class="mb-8 text-slate-500">Материалы магазина</p>

    @if ($posts->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center text-slate-500">
            Пока нет опубликованных материалов
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <a href="{{ route('posts.show', ['postSlug' => $post->slug]) }}"
                   class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="aspect-[16/10] overflow-hidden bg-slate-100">
                        @if ($post->cover_url)
                            <img src="{{ $post->cover_url }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        @else
                            <div class="flex h-full items-center justify-center text-4xl text-slate-300">📄</div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            {{ $post->typeLabel() }} · {{ $post->published_at?->format('d.m.Y') }}
                        </p>
                        <h2 class="mt-2 text-lg font-bold line-clamp-2">{{ $post->title }}</h2>
                        @if ($post->excerpt)
                            <p class="mt-2 text-sm text-slate-500 line-clamp-3">{{ $post->excerpt }}</p>
                        @endif
                        <span class="mt-auto pt-4 text-sm font-semibold text-primary">Читать →</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    @endif
@endsection
