@props(['url' => '#'])

@if ($url && $url !== '#')
    <a href="{{ $url }}"
       target="_blank"
       rel="noopener"
       class="fi-btn inline-flex items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:hover:bg-white/10">
        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-4 w-4" />
        <span>Перейти на сайт</span>
    </a>
@endif