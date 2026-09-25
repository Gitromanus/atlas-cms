@php
    /** @var \App\Models\Product|null $record */
    $variants = $getRecord()
        ?->variants
        ->filter(fn ($variant) => filled($variant->options))
        ->values() ?? collect();
@endphp

@if ($variants->isEmpty())
    <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        Вариантов нет — это обычный товар, цена и остаток задаются в разделе «Цены и остатки».
    </div>
@else
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Вариант</th>
                    <th class="px-3 py-2 font-medium text-right">Цена</th>
                    <th class="px-3 py-2 font-medium text-right">Остаток</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($variants as $variant)
                    <tr>
                        <td class="px-3 py-2">
                            {{ collect($variant->options)->map(fn ($value, $name) => $name.': '.$value)->implode(' • ') }}
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            {{ $variant->price !== null ? number_format((float) $variant->price, 2, ',', ' ').' ₽' : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            {{ number_format((float) $variant->quantity, 0, ',', ' ') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif