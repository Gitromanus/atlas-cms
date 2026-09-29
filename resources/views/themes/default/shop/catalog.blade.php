@extends('layouts.shop')

@section('title', $category?->name ?? 'Каталог')

@section('content')
    <nav class="mb-2 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-primary">Главная</a>
        @if ($category)
            → <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>
        @endif
    </nav>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ $category?->name ?? 'Каталог товаров' }}</h1>
        @if ($products->total() > 0)
            <p class="text-sm text-slate-500">
                Найдено: <span class="font-semibold text-slate-700">{{ $products->total() }}</span>
            </p>
        @endif
    </div>

    @if ($categories->isNotEmpty())
        <nav class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('catalog.index') }}"
               class="rounded-full px-4 py-1.5 text-sm font-medium transition @if (! $category) bg-primary text-white shadow-sm @else bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 @endif">
                Все товары
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('catalog.category', $cat->slug ?: $cat->id) }}"
                   class="rounded-full px-4 py-1.5 text-sm font-medium transition @if ($category?->id === $cat->id) bg-primary text-white shadow-sm @else bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 @endif">
                    {{ $cat->name }}
                    <span class="opacity-60">({{ $cat->products_count_total ?? $cat->products_count ?? 0 }})</span>
                </a>
            @endforeach
        </nav>
    @endif

    @if ($categoryNode !== null && filled($categoryNode->children ?? null) && $categoryNode->children->isNotEmpty())
        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($categoryNode->children as $child)
                <a href="{{ route('catalog.category', $child->slug ?: $child->id) }}"
                   class="group rounded-theme border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                    <span class="font-medium group-hover:text-primary">{{ $child->name }}</span>
                    <span class="block text-xs text-slate-400">{{ $child->products_count_total ?? $child->products_count ?? 0 }} тов.</span>
                </a>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ url()->current() }}" id="catalog-filters">
        @php
            $colorMap = [
                'белый' => '#ffffff', 'белая' => '#ffffff', 'белое' => '#ffffff', 'white' => '#ffffff',
                'чёрный' => '#111111', 'черный' => '#111111', 'чёрная' => '#111111', 'черная' => '#111111', 'black' => '#111111',
                'серый' => '#9ca3af', 'серая' => '#9ca3af', 'gray' => '#9ca3af', 'grey' => '#9ca3af',
                'красный' => '#ef4444', 'красная' => '#ef4444', 'red' => '#ef4444',
                'синий' => '#3b82f6', 'синяя' => '#3b82f6', 'blue' => '#3b82f6',
                'голубой' => '#38bdf8', 'голубая' => '#38bdf8',
                'зелёный' => '#22c55e', 'зеленый' => '#22c55e', 'зелёная' => '#22c55e', 'зеленая' => '#22c55e', 'green' => '#22c55e',
                'жёлтый' => '#eab308', 'желтый' => '#eab308', 'yellow' => '#eab308',
                'оранжевый' => '#f97316', 'orange' => '#f97316',
                'розовый' => '#ec4899', 'pink' => '#ec4899',
                'фиолетовый' => '#a855f7', 'purple' => '#a855f7',
                'коричневый' => '#92400e', 'brown' => '#92400e',
                'бежевый' => '#d6c3a8', 'beige' => '#d6c3a8',
                'бордовый' => '#9f1239', 'burgundy' => '#9f1239',
                'хаки' => '#78716c', 'khaki' => '#78716c',
                'оливковый' => '#65a30d', 'olive' => '#65a30d',
                'золотой' => '#ca8a04', 'gold' => '#ca8a04',
                'серебряный' => '#cbd5e1', 'silver' => '#cbd5e1',
                'мультиколор' => 'linear-gradient(135deg,#ef4444,#eab308,#22c55e,#3b82f6)',
                'разноцветный' => 'linear-gradient(135deg,#ef4444,#eab308,#22c55e,#3b82f6)',
            ];
            $resolveColor = function (string $value) use ($colorMap): ?string {
                $key = mb_strtolower(trim($value));
                if (isset($colorMap[$key])) {
                    return $colorMap[$key];
                }
                foreach ($colorMap as $name => $hex) {
                    if (str_contains($key, $name) || str_contains($name, $key)) {
                        return $hex;
                    }
                }
                if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
                    return str_starts_with($value, '#') ? $value : '#'.$value;
                }

                return null;
            };
            $activeFilterCount = collect($selectedFilters)->flatten()->count();
        @endphp

        <div class="grid items-start gap-5 lg:grid-cols-[220px_1fr]">
            @if ($filterOptions !== [])
                <aside class="rounded-theme border border-slate-200 bg-white lg:sticky lg:top-20"
                       x-data="{ open: window.matchMedia('(min-width: 1024px)').matches }">
                    <button type="button"
                            class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left lg:pointer-events-none"
                            @click="open = !open"
                            :class="open && 'border-b border-slate-100'">
                        <span class="flex items-center gap-2 text-sm font-bold text-slate-800">
                            Фильтры
                            @if ($activeFilterCount > 0)
                                <span class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $activeFilterCount }}</span>
                            @endif
                        </span>
                        <span class="flex items-center gap-2">
                            @if ($activeFilterCount > 0)
                                <a href="{{ url()->current() }}" class="pointer-events-auto text-xs font-medium text-slate-400 hover:text-primary" @click.stop>Сбросить</a>
                            @endif
                            <svg class="h-4 w-4 text-slate-400 transition lg:hidden" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </span>
                    </button>

                    <div class="space-y-4 px-4 py-3" x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        @foreach ($filterOptions as $name => $option)
                            @php $type = $option['type'] ?? 'default'; @endphp
                            <div>
                                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $name }}</p>

                                @if ($type === 'color')
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($option['values'] as $row)
                                            @php
                                                $value = is_array($row) ? $row['value'] : $row;
                                                $count = is_array($row) ? ($row['count'] ?? null) : null;
                                                $hex = $resolveColor($value);
                                                $checked = in_array($value, $selectedFilters[$name] ?? [], true);
                                            @endphp
                                            <label class="group relative cursor-pointer" title="{{ $value }}{{ $count !== null ? ' ('.$count.')' : '' }}">
                                                <input type="checkbox" name="f[{{ $name }}][]" value="{{ $value }}" class="peer sr-only"
                                                       @checked($checked) onchange="this.form.submit()">
                                                @if ($hex)
                                                    <span class="block h-8 w-8 rounded-lg border-2 transition peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary/30 {{ $checked ? 'border-primary' : 'border-slate-200 hover:border-slate-300' }}"
                                                          style="background: {{ $hex }};"></span>
                                                @else
                                                    <span class="flex h-8 min-w-8 items-center justify-center rounded-lg border-2 bg-slate-100 px-1 text-[9px] font-bold text-slate-600 transition peer-checked:border-primary peer-checked:ring-2 peer-checked:ring-primary/30 {{ $checked ? 'border-primary' : 'border-slate-200' }}">{{ mb_substr($value, 0, 2) }}</span>
                                                @endif
                                                @if ($count !== null)
                                                    <span class="pointer-events-none absolute -right-1 -top-1 rounded bg-slate-800 px-1 text-[9px] font-bold text-white opacity-0 transition group-hover:opacity-100">{{ $count }}</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>

                                @elseif ($type === 'size' || $type === 'brand')
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($option['values'] as $row)
                                            @php
                                                $value = is_array($row) ? $row['value'] : $row;
                                                $count = is_array($row) ? ($row['count'] ?? null) : null;
                                                $checked = in_array($value, $selectedFilters[$name] ?? [], true);
                                            @endphp
                                            <label class="cursor-pointer">
                                                <input type="checkbox" name="f[{{ $name }}][]" value="{{ $value }}" class="peer sr-only"
                                                       @checked($checked) onchange="this.form.submit()">
                                                <span class="inline-flex min-w-[2.25rem] items-center justify-center gap-1 rounded-lg border px-2 py-1.5 text-xs font-semibold transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white {{ $checked ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' }}">
                                                    {{ $value }}
                                                    @if ($count !== null)
                                                        <span class="text-[10px] font-normal opacity-70">{{ $count }}</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                @else
                                    <div class="max-h-40 space-y-0.5 overflow-y-auto pr-1">
                                        @foreach ($option['values'] as $row)
                                            @php
                                                $value = is_array($row) ? $row['value'] : $row;
                                                $count = is_array($row) ? ($row['count'] ?? null) : null;
                                                $checked = in_array($value, $selectedFilters[$name] ?? [], true);
                                            @endphp
                                            <label class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-1.5 py-1 text-sm text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                                <span class="flex min-w-0 items-center gap-2">
                                                    <input type="checkbox" name="f[{{ $name }}][]" value="{{ $value }}"
                                                           class="h-3.5 w-3.5 shrink-0 rounded border-slate-300 accent-[var(--color-primary)]"
                                                           @checked($checked) onchange="this.form.submit()">
                                                    <span class="truncate">{{ $value }}</span>
                                                </span>
                                                @if ($count !== null)
                                                    <span class="shrink-0 text-[11px] tabular-nums text-slate-400">{{ $count }}</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </aside>
            @endif

            <div>
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full max-w-sm">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Поиск по каталогу..."
                               class="w-full rounded-theme border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        Сортировка:
                        <select name="sort" onchange="this.form.submit()"
                                class="rounded-theme border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none">
                            <option value="new" @selected($sort === 'new' || $sort === '')>Сначала новинки</option>
                            <option value="popular" @selected($sort === 'popular')>По популярности</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Сначала дешевле</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Сначала дороже</option>
                            <option value="name_asc" @selected($sort === 'name_asc')>По алфавиту (А–Я)</option>
                            <option value="name_desc" @selected($sort === 'name_desc')>По алфавиту (Я–А)</option>
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($products as $product)
                        @include('shop.partials.product-card', ['product' => $product])
                    @empty
                        <div class="col-span-full rounded-theme border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                            Ничего не найдено. Попробуйте изменить запрос или сбросить фильтры.
                        </div>
                    @endforelse
                </div>

                @if ($products->hasPages())
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </form>
@endsection
