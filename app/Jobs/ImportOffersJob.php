<?php

namespace App\Jobs;

use App\Models\ExchangeLog;
use App\Models\Tenant;
use App\Services\CommerceML\ExchangeStore;
use App\Services\CommerceML\OffersXmlParser;
use App\Services\Tenant\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Импорт предложений (offers.xml): цены и остатки — в фоне.
 */
class ImportOffersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public int $tenantId,
        public string $filename,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);

        try {
            $store = new ExchangeStore($tenant);
            $count = (new OffersXmlParser($store))->import($this->filename);

            ExchangeLog::query()->create([
                'tenant_id' => $tenant->id,
                'type' => 'catalog',
                'mode' => 'import',
                'filename' => $this->filename,
                'status' => 'success',
                'message' => "Импортировано предложений: {$count}",
            ]);

            $store->deleteFile($this->filename);
        } catch (\Throwable $e) {
            Log::error('ImportOffersJob failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            ExchangeLog::query()->create([
                'tenant_id' => $tenant->id,
                'type' => 'catalog',
                'mode' => 'import',
                'filename' => $this->filename,
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}