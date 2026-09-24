<?php

namespace App\Jobs;

use App\Models\ExchangeLog;
use App\Models\Tenant;
use App\Services\CommerceML\ExchangeStore;
use App\Services\CommerceML\ImportXmlParser;
use App\Services\Tenant\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Импорт каталога (import.xml) в фоне.
 */
class ImportCatalogJob implements ShouldQueue
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
            $count = (new ImportXmlParser($store))->import($this->filename);

            ExchangeLog::query()->create([
                'tenant_id' => $tenant->id,
                'type' => 'catalog',
                'mode' => 'import',
                'filename' => $this->filename,
                'status' => 'success',
                'message' => "Импортировано товаров: {$count}",
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportCatalogJob failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

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