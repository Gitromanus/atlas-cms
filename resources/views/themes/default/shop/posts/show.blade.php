@extends('layouts.shop')

@section('title', $post->title)

@section('content')
    <nav class="mb-6 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-primary">Главная</a>
        <span class="mx-1">/</span>
        <a href="{{ $post->type === 'news' ? route('news.index') : route('articles.index') }}" class="hover:text-primary">
            {{ $post->typeLabel() }}
        </a>
        <span class="mx-1">/</span>
        <span class="text-slate-800">{{ $post->title }}</span>
    </nav>

    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ $post->typeLabel() }}</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight md:text-4xl">{{ $post->title }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $post->published_at?->format('d.m.Y H:i') }}</p>

        @if ($post->cover_url)
            <img src="{{ $post->cover_url }}" alt="" class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover shadow-sm">
        @endif

        @if ($post->excerpt)
            <p class="mt-6 text-lg text-slate-600">{{ $post->excerpt }}</p>
        @endif

        <div class="prose prose-slate mt-8 max-w-none">
            {!! $post->body !!}
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-16 max-w-3xl border-t border-slate-200 pt-10">
            <h2 class="mb-4 text-xl font-bold">Ещё материалы</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($related as $item)
                    <a href="{{ route('posts.show', ['postSlug' => $item->slug]) }}" class="rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold hover:border-primary/40">
                        {{ $item->title }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
