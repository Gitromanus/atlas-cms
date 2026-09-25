@php
    /** @var \App\Models\Product|null $record */
    $urls = $getRecord()
        ?->images
        ->sortBy('sort_order')
        ->pluck('url')
        ->filter()
        ->values()
        ->all() ?? [];
@endphp

@if ($urls === [])
    <div class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        Нет изображений. Загрузите новые файлы кнопкой ниже.
    </div>
@else
    <div x-data="{ urls: @js($urls), active: 0 }" class="space-y-2">
        <img :src="urls[active]" alt=""
             class="aspect-square w-full rounded-lg object-cover"
             style="max-height: 260px">
        @if (count($urls) > 1)
            {{-- Компактные миниатюры: ~3 в ряд, остальные — горизонтальной прокруткой --}}
            <div class="flex gap-1.5 overflow-x-auto pb-1">
                <template x-for="(url, i) in urls" :key="i">
                    <img :src="url" alt="" @click="active = i"
                         class="h-16 w-16 shrink-0 cursor-pointer rounded object-cover ring-1 ring-gray-200"
                         :class="active === i ? 'ring-2 ring-primary-500' : ''">
                </template>
            </div>
        @endif
    </div>
@endif