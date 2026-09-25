<?php

namespace App\Filament\Shop\Resources\OrderResource\Pages;

use App\Filament\Shop\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Экспорт CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $filename = 'orders-'.now()->format('Y-m-d-His').'.csv';

                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                        fputcsv($out, ['Номер', 'Дата', 'Покупатель', 'Телефон', 'Email', 'Сумма', 'Статус', 'Оплачен'], ';');

                        Order::query()
                            ->with('status')
                            ->orderByDesc('id')
                            ->chunk(200, function ($orders) use ($out) {
                                foreach ($orders as $order) {
                                    fputcsv($out, [
                                        $order->number,
                                        $order->placed_at?->format('d.m.Y H:i'),
                                        $order->customer_name,
                                        $order->customer_phone,
                                        $order->customer_email,
                                        $order->total,
                                        $order->status?->name,
                                        $order->is_paid ? 'да' : 'нет',
                                    ], ';');
                                }
                            });

                        fclose($out);
                    }, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }
}
