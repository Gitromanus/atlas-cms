@php
    $path = $get('../path') ?? $get('path');
    $urlField = $get('../url') ?? $get('url');
    $src = null;
    if (filled($urlField)) {
        $src = $urlField;
    } elseif (filled($path)) {
        $relative = str_starts_with((string) $path, 'products/') ? $path : 'products/'.$path;
        $src = asset('storage/'.$relative);
    }
@endphp

@if ($src)
    <div class="space-y-1">
        <img src="{{ $src }}" alt="" class="h-28 w-full rounded-lg object-cover ring-1 ring-gray-200 dark:ring-white/10">
        <p class="truncate text-[10px] text-gray-400">{{ $path ?? $urlField }}</p>
    </div>
@else
    <div class="flex h-28 items-center justify-center rounded-lg border border-dashed border-gray-300 text-xs text-gray-400 dark:border-white/10">
        нет превью
    </div>
@endif
