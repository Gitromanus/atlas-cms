<?php

namespace App\Http\Controllers\OneC;

use App\Http\Controllers\Controller;
use App\Jobs\ImportCatalogJob;
use App\Jobs\ImportOffersJob;
use App\Models\ExchangeLog;
use App\Models\Tenant;
use App\Services\CommerceML\ExchangeStore;
use App\Services\CommerceML\OrderStatusImporter;
use App\Services\CommerceML\OrdersExporter;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class ExchangeController extends Controller
{
    public function handle(Request $request): Response
    {
        $tenant = app(TenantContext::class)->current();

        if ($tenant === null) {
            return response('failure');
        }

        $type = (string) $request->query('type', 'catalog');
        $mode = (string) $request->query('mode');

        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);

        try {
            if ($mode === 'checkauth') {
                return $this->checkauth($request, $tenant);
            }

            $store = new ExchangeStore($tenant);

            $authorized = $this->authorize($request, $tenant);
            $sessionValid = $store->hasValidSession((string) $request->query('session_id'), $type);

            if (! $authorized && ! $sessionValid) {
                \Illuminate\Support\Facades\Log::info('1C: отклонён запрос без валидных учётных данных и сессии', [
                    'mode' => $mode,
                    'session_id' => $request->query('session_id'),
                    'type' => $type,
                    'url' => $request->fullUrl(),
                ]);
                $this->log($tenant, $type, $mode, $request->query('filename'), 'failure', 'Нет валидных учётных данных или сессии');

                return response('failure');
            }

            return match ($mode) {
                'init' => $this->init($store),
                'file' => $this->file($request, $store, $type),
                'import' => $this->import($request, $store, $type),
                'query' => $this->queryOrders($store),
                'success' => $this->saleSuccess($store),
                'failure' => $this->saleFailure($tenant, $type),
                default => response('failure'),
            };
        } catch (\Throwable $e) {
            try {
                $this->log($tenant, $type, $mode, $request->query('filename'), 'failure', mb_substr($e->getMessage(), 0, 2000));
            } catch (\Throwable) {
            }
            report($e);

            return response("failure\n".$e->getMessage(), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    protected function authorize(Request $request, Tenant $tenant): bool
    {
        $login = $request->getUser();
        $password = $request->getPassword();

        $owner = $tenant->owner;

        return $owner !== null
            && $login !== null
            && hash_equals(strtolower((string) $owner->email), strtolower($login))
            && Hash::check((string) $password, (string) $owner->password);
    }

    protected function checkauth(Request $request, Tenant $tenant): Response
    {
        if (! $this->authorize($request, $tenant)) {
            $this->log($tenant, (string) $request->query('type', 'catalog'), 'checkauth', null, 'failure', 'Неверные учётные данные обмена');

            return response('failure');
        }

        $type = (string) $request->query('type', 'catalog');
        $store = new ExchangeStore($tenant);
        $sessionId = $store->startSession($type);

        $store->set('received_files', []);

        $this->log($tenant, $type, 'checkauth', null, 'success', 'Авторизация успешна');

        return response("success\n{$sessionId}");
    }

    protected function init(ExchangeStore $store): Response
    {
        $limit = (int) config('atlas.onec.file_limit', 52428800);

        // zip=no — 1С шлёт файлы поштучно, меньше риск 500 на shared из-за большого zip
        return $this->plain("zip=no\nfile_limit={$limit}");
    }

    protected function file(Request $request, ExchangeStore $store, string $type): Response
    {
        $filename = (string) $request->query('filename');
        $filename = str_replace(chr(92), '/', $filename);

        if ($filename === '') {
            return $this->plain("failure\nempty filename");
        }

        $received = (array) $store->get('received_files', []);
        $isFirstChunk = ! in_array($filename, $received, true);

        if ($isFirstChunk) {
            if ($store->hasFile($filename)) {
                $store->deleteFile($filename);
            }
            $received[] = $filename;
            if ($this->shouldLogFile($filename)) {
                $store->set('received_files', $received);
            } else {
                $store->set('received_files', array_slice($received, -50));
            }
        }

        $bytes = $store->appendStream($filename, fopen('php://input', 'rb'));

        if ($bytes === 0 && $isFirstChunk) {
            $contents = $request->getContent();
            if (is_string($contents) && $contents !== '') {
                $store->appendToFile($filename, $contents);
                $bytes = strlen($contents);
            }
        }

        if ($bytes === 0) {
            return $this->plain("failure\nempty body");
        }

        if ($this->shouldLogFile($filename)) {
            $this->log($store, $type, 'file', $filename, 'success', "Файл получен ({$bytes} байт)");
        }

        return $this->plain('success');
    }

    protected function shouldLogFile(string $filename): bool
    {
        $base = strtolower(basename(str_replace(chr(92), '/', $filename)));

        return str_ends_with($base, '.xml')
            || str_ends_with($base, '.zip')
            || str_contains($base, 'import')
            || str_contains($base, 'offers')
            || str_contains($base, 'orders');
    }

    protected function plain(string $body, int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function import(Request $request, ExchangeStore $store, string $type): Response
    {
        $filename = (string) $request->query('filename');
        $filename = str_replace(chr(92), '/', $filename);

        if ($filename === '' || ! $store->hasFile($filename)) {
            return response('failure');
        }

        $tenant = app(TenantContext::class)->current();
        $this->log($store, $type, 'import', $filename, 'processing', 'Файл поставлен в очередь на обработку');

        if ($type === 'sale') {
            $count = (new OrderStatusImporter($store))->import($filename);
            $this->log($store, $type, 'import', $filename, 'success', "Обновлено заказов: {$count}");
            $store->deleteFile($filename);

            return response('success');
        }

        if (str_ends_with(strtolower($filename), '.zip')) {
            $count = $store->extractZip($filename);
            $this->log($store, $type, 'import', $filename, 'success', "Распаковано файлов: {$count}");
            $store->deleteFile($filename);

            return response('success');
        }

        $isOffers = str_contains(strtolower($filename), 'offers');

        if (config('atlas.onec.sync_import', false)) {
            try {
                $job = $isOffers
                    ? new ImportOffersJob($tenant->id, $filename)
                    : new ImportCatalogJob($tenant->id, $filename);

                $job->handle();
            } catch (\Throwable $e) {
                return response('failure');
            }

            return response('success');
        }

        if ($isOffers) {
            ImportOffersJob::dispatch($tenant->id, $filename);
        } else {
            ImportCatalogJob::dispatch($tenant->id, $filename);
        }

        return response('success');
    }

    protected function queryOrders(ExchangeStore $store): Response
    {
        $xml = (new OrdersExporter($store))->export();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    protected function saleSuccess(ExchangeStore $store): Response
    {
        $ids = $store->get('pending_order_ids', []);

        if (! empty($ids)) {
            \App\Models\Order::query()
                ->whereIn('id', $ids)
                ->update(['exported_to_1c' => true]);
        }

        $store->set('pending_order_ids', []);
        $this->log($store, 'sale', 'success', null, 'success', 'Заказы переданы в 1С');

        return response('success');
    }

    protected function saleFailure(Tenant $tenant, string $type): Response
    {
        $this->log($tenant, $type, 'failure', null, 'failure', 'Ошибка на стороне 1С');

        return response('success');
    }

    protected function log(ExchangeStore|Tenant $context, string $type, string $mode, ?string $filename, string $status, string $message): void
    {
        $tenantId = $context instanceof ExchangeStore ? $context->getTenantId() : $context->id;

        ExchangeLog::query()->create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'mode' => $mode,
            'filename' => $filename !== null ? mb_substr((string) $filename, 0, 240) : null,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
