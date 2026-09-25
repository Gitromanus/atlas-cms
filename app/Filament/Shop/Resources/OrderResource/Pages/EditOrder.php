<?php
namespace App\Filament\Shop\Resources\OrderResource\Pages;
use App\Filament\Shop\Resources\OrderResource;
use App\Mail\OrderStatusChanged; use App\Models\OrderStatus;
use App\Services\Tenant\TenantContext;
use Filament\Actions; use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log; use Illuminate\Support\Facades\Mail;
class EditOrder extends EditRecord {
    protected static string $resource = OrderResource::class;
    protected ?int $oldStatusId = null;
    protected function beforeSave(): void { $this->oldStatusId = $this->record->status_id; }
    protected function afterSave(): void {
        if ($this->oldStatusId === null || (int)$this->oldStatusId === (int)$this->record->status_id) return;
        if (blank($this->record->customer_email)) return;
        $status = OrderStatus::query()->find($this->record->status_id);
        $tenant = app(TenantContext::class)->current() ?? $this->record->tenant;
        if (!$status || !$tenant) return;
        try { Mail::to($this->record->customer_email)->send(new OrderStatusChanged($this->record->load('items'), $tenant, $status->name)); }
        catch (\Throwable $e) { Log::warning('OrderStatusChanged: '.$e->getMessage()); }
    }
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
