@props(['category', 'menuLevel' => 0])

@php
    $url = route('catalog.category', $category->slug ?: $category->id);
    $count = $category->products_count_total ?? $category->products_count ?? 0;
    $hasChildren = filled($category->children ?? null) && $category->children->isNotEmpty();
@endphp

<div class="group relative">
    <a href="{{ $url }}"
       class="flex items-center gap-1.5 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 hover:text-primary">
        <span>{{ $category->name }}</span>
        @if ($count > 0)
            <span class="text-xs font-normal text-slate-400">({{ $count }})</span>
        @endif
        @if ($hasChildren)
            <svg class="h-3 w-3 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.17 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
            </svg>
        @endif
    </a>

    @if ($hasChildren)
        {{-- menuLevel 0: выпадающее меню вниз; вложенные уровни: flyout вправо --}}
        <div class="{{ $menuLevel === 0 ? 'left-0 top-full pt-1' : 'left-full top-0 pl-1' }} invisible absolute z-50 min-w-56 opacity-0 transition group-hover:visible group-hover:opacity-100">
            <div class="rounded-theme border border-slate-200 bg-white py-1 shadow-xl">
                @foreach ($category->children as $child)
                    @include('shop.partials.category-menu', ['category' => $child, 'menuLevel' => 1])
                @endforeach
            </div>
        </div>
    @endif
</div>