<div>
    @php
        $record = $getRecord();
        $path = $record ? data_get($record, 'path') : null;
        $externalUrl = $record ? data_get($record, 'url') : null;
        $src = filled($path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url((string) $path)
            : $externalUrl;
    @endphp

    @if (filled($src))
        <img
            src="{{ $src }}"
            alt="Изображение товара"
            style="max-width: 220px; max-height: 140px; object-fit: contain; border-radius: 0.5rem; border: 1px solid #e2e8f0;"
        >
    @else
        <p class="text-sm text-gray-400">Изображение не задано</p>
    @endif
</div>