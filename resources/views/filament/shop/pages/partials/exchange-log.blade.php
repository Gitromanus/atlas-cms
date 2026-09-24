<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
        <tr>
            <th class="px-4 py-2.5">Время</th>
            <th class="px-4 py-2.5">Тип</th>
            <th class="px-4 py-2.5">Режим</th>
            <th class="px-4 py-2.5">Файл</th>
            <th class="px-4 py-2.5">Статус</th>
            <th class="px-4 py-2.5">Сообщение</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($logs as $log)
            <tr class="border-b border-gray-100 last:border-0">
                <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                <td class="px-4 py-2">{{ $log->type === 'catalog' ? 'Каталог' : 'Заказы' }}</td>
                <td class="px-4 py-2">{{ $log->mode }}</td>
                <td class="px-4 py-2">{{ $log->filename }}</td>
                <td class="px-4 py-2">
                    @if ($log->status === 'success')
                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Успех</span>
                    @elseif ($log->status === 'failure')
                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Ошибка</span>
                    @else
                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">В обработке</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-gray-600">{{ $log->message }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                    Записей обмена пока нет. Настройте выгрузку в 1С — журнал заполнится автоматически.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>