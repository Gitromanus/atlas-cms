<?php

namespace App\Filament\Shop\Widgets;

use App\Filament\Shop\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Последние заказы';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['status'])
                    ->latest('placed_at')
                    ->latest('id')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('№')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Покупатель')
                    ->limit(24),
                Tables\Columns\TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Статус')
                    ->badge(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Открыть')
                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
