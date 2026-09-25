@props(['categories' => collect()])

@foreach ($categories as $category)
    <div class="group relative">
        <a href="{{ route('catalog.category', $category->slug ?: $category->id) }}"
           class="flex items-center justify-between gap-4 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
            <span>{{ $category->name }}</span>
            <span class="shrink-0 text-xs text-slate-400">({{ $category->products_count_total ?? $category->products_count ?? 0 }})</span>
        </a>

        @if (filled($category->children ?? null) && $category->children->isNotEmpty())
            <div class="absolute left-full top-0 z-50 hidden pl-1 group-hover:block">
                <div class="min-w-52 rounded-theme border border-slate-200 bg-white py-1 shadow-lg">
                    @include('shop.partials.category-menu', ['categories' => $category->children])
                </div>
            </div>
        @endif
    </div>
@endforeach