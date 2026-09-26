<?php

namespace App\Console\Commands;

use App\Models\DeliveryMethod;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SetupYandexDeliveryTest extends Command
{
    protected $signature = 'atlas:yandex-test
        {--tenant= : slug магазина (по умолчанию — все)}
        {--force : перезаписать уже заполненный токен}';

    protected $description = 'Подключить тестовый контур Яндекс Доставки (токен + способ yandex)';

    public function handle(): int
    {
        $slug = $this->option('tenant');
        $force = (bool) $this->option('force');

        $query = Tenant::query();
        if ($slug) {
            $query->where(function ($q) use ($slug) {
                $q->where('slug', $slug)->orWhere('subdomain', $slug);
            });
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->error('Магазины не найдены');

            return self::FAILURE;
        }

        $token = (string) config('services.yandex_delivery.test_token');
        $source = (string) config('services.yandex_delivery.test_source_address');
        $lon = config('services.yandex_delivery.test_source_lon');
        $lat = config('services.yandex_delivery.test_source_lat');

        foreach ($tenants as $tenant) {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $hasToken = filled($settings['yandex_delivery_token'] ?? null);

            if ($hasToken && ! $force) {
                $this->line("[{$tenant->slug}] токен уже задан — пропуск (или --force)");
            } else {
                $settings['yandex_delivery_token'] = $token;
                $settings['yandex_delivery_source_address'] = $source;
                $settings['yandex_delivery_source_lon'] = $lon;
                $settings['yandex_delivery_source_lat'] = $lat;
                $settings['yandex_delivery_taxi_class'] = 'express';
                $settings['yandex_delivery_test_mode'] = true;
                $tenant->forceFill(['settings' => $settings])->save();
                $this->info("[{$tenant->slug}] тестовый токен и адрес склада записаны");
            }

            $method = DeliveryMethod::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'yandex')
                ->first();

            if ($method === null) {
                DeliveryMethod::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Яндекс Доставка (курьер)',
                    'code' => 'yandex',
                    'description' => 'Расчёт через API Яндекс Доставки',
                    'price' => 0,
                    'free_from' => null,
                    'require_address' => true,
                    'is_active' => true,
                    'sort_order' => 10,
                ]);
                $this->info("[{$tenant->slug}] способ доставки code=yandex создан");
            } else {
                $method->update(['is_active' => true, 'require_address' => true]);
                $this->line("[{$tenant->slug}] способ yandex уже есть — активирован");
            }
        }

        $this->info('Готово. На checkout выберите «Яндекс Доставка» и адрес в Москве.');

        return self::SUCCESS;
    }
}
