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

/**
 * Обмен с 1С по протоколу CommerceML 2.09.
 *
 * Точка входа: /1c/exchange
 * Параметры: type (catalog|sale), mode (checkauth|init|file|import|query|success|failure)
 *
 * Авторизация: HTTP Basic (логин/пароль из настроек магазина).
 */
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

        try {
            return match ($mode) {
                'checkauth' => $this->checkauth($request, $tenant),
                'init' => $this->guardSession($request, $tenant, fn (ExchangeStore $store) => $this->init($store)),
                'file' => $this->guardSession($request, $tenant, fn (ExchangeStore $store) => $this->file($request, $store, $type)),
                'import' => $this->guardSession($request, $tenant, fn (ExchangeStore $store) => $this->import($request, $store, $type)),
                'query' => $this->guardSession($request, $tenant, fn (ExchangeStore $store) => $this->queryOrders($store)),
                'success' => $this->guardSession($request, $tenant, fn (ExchangeStore $store) => $this->saleSuccess($store)),
                'failure' => $this->saleFailure($tenant, $type),
                default => response('failure'),
            };
        } catch (\Throwable $e) {
            $this->log($tenant, $type, $mode, $request->query('filename'), 'failure', $e->getMessage());
            report($e);

            return response('failure');
        }
    }

    /**
     * mode=checkauth — проверка авторизации и старт сессии обмена.
     */
    protected function checkauth(Request $request, Tenant $tenant): Response
    {
        $login = $request->getUser();
        $password = $request->getPassword();

        $expectedLogin = $tenant->setting('exchange.login');
        $expectedPassword = $tenant->setting('exchange.password');

        if ($login === null || $expectedLogin === null
            || ! hash_equals((string) $expectedLogin, (string) $login)
            || ! Hash::check((string) $password, (string) $expectedPassword)) {
            $this->log($tenant, (string) $request->query('type', 'catalog'), 'checkauth', null, 'failure', 'Неверные учётные данные обмена');

            return response('failure');
        }

        $sessionId = (new ExchangeStore($tenant))->startSession();
        $this->log($tenant, (string) $request->query('type', 'catalog'), 'checkauth', null, 'success', 'Авторизация успешна');

        return response("success\n{$sessionId}");
    }

    /**
     * mode=init — параметры сессии.
     */
    protected function init(ExchangeStore $store): Response
    {
        $limit = config('atlas.onec.file_limit');

        return response("zip=no\nfile_limit={$limit}");
    }

    /**
     * mode=file — приём файла от 1С (частями).
     */
    protected function file(Request $request, ExchangeStore $store, string $type): Response
    {
        $filename = (string) $request->query('filename');

        if ($filename === '') {
            return response('failure');
        }

        $contents = $request->getContent();

        if ($contents === '') {
            return response('failure');
        }

        $store->appendToFile($filename, $contents);
        $this->log($store, $type, 'file', $filename, 'success', 'Файл получен');

        return response('success');
    }

    /**
     * mode=import — обработка принятого файла.
     */
    protected function import(Request $request, ExchangeStore $store, string $type): Response
    {
        $filename = (string) $request->query('filename');

        if ($filename === '' || ! $store->hasFile($filename)) {
            return response('failure');
        }

        $tenant = app(TenantContext::class)->current();
        $this->log($store, $type, 'import', $filename, 'processing', 'Файл поставлен в очередь на обработку');

        if ($type === 'sale') {
            // Статусы заказов от 1С
            $count = (new OrderStatusImporter($store))->import($filename);
            $this->log($store, $type, 'import', $filename, 'success', "Обновлено заказов: {$count}");

            return response('success');
        }

        // Каталог: запускаем в фоне
        $isOffers = str_contains(strtolower($filename), 'offers');

        if ($isOffers) {
            ImportOffersJob::dispatch($tenant->id, $filename);
        } else {
            ImportCatalogJob::dispatch($tenant->id, $filename);
        }

        return response('success');
    }

    /**
     * type=sale&mode=query — 1С запрашивает заказы.
     */
    protected function queryOrders(ExchangeStore $store): Response
    {
        $xml = (new OrdersExporter($store))->export();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * type=sale&mode=success — 1С подтвердила получение заказов.
     */
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

    /**
     * Проверка валидности сессии перед каждым режимом (кроме checkauth).
     */
    protected function guardSession(Request $request, Tenant $tenant, callable $callback): Response
    {
        $store = new ExchangeStore($tenant);
        $sessionId = $request->query('session_id');

        if (! $store->hasValidSession((string) $sessionId)) {
            return response('failure');
        }

        return $callback($store);
    }

    protected function log(ExchangeStore|Tenant $context, string $type, string $mode, ?string $filename, string $status, string $message): void
    {
        $tenantId = $context instanceof ExchangeStore ? $context->getTenantId() : $context->id;

        ExchangeLog::query()->create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'mode' => $mode,
            'filename' => $filename,
            'status' => $status,
            'message' => $message,
        ]);
    }
}