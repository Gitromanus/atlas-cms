<?php

namespace App\Services\CommerceML;

use App\Models\DeliveryMethod;
use App\Models\OrderStatus;

/**
 * Справочники для 1С: type=sale&mode=info
 */
class SaleInfoExporter
{
    public function __construct(protected ExchangeStore $store) {}

    public function export(): string
    {
        $tenantId = $this->store->getTenantId();

        $statuses = OrderStatus::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();

        if ($statuses->isEmpty()) {
            foreach ([
                ['code' => 'new', 'name' => 'Новый'],
                ['code' => 'processing', 'name' => 'В обработке'],
                ['code' => 'shipped', 'name' => 'Отправлен'],
                ['code' => 'completed', 'name' => 'Выполнен'],
                ['code' => 'cancelled', 'name' => 'Отменён'],
            ] as $row) {
                OrderStatus::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'code' => $row['code']],
                    ['name' => $row['name'], 'is_system' => true]
                );
            }
            $statuses = OrderStatus::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get();
        }

        $deliveries = DeliveryMethod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $e = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $date = now()->format('Y-m-d\TH:i:s');

        $statusItems = '';
        foreach ($statuses as $s) {
            $id = $e((string) ($s->ext_code ?: $s->code ?: $s->id));
            $name = $e((string) $s->name);
            $statusItems .= "      <Элемент><Ид>{$id}</Ид><Название>{$name}</Название><Наименование>{$name}</Наименование></Элемент>\n";
        }

        $payItems = '';
        foreach ([
            ['id' => 'cash', 'name' => 'Наличными'],
            ['id' => 'card_online', 'name' => 'Картой онлайн'],
            ['id' => 'card_courier', 'name' => 'Картой курьеру'],
        ] as $ps) {
            $id = $e($ps['id']);
            $name = $e($ps['name']);
            $payItems .= "      <Элемент><Ид>{$id}</Ид><Название>{$name}</Название><Наименование>{$name}</Наименование></Элемент>\n";
        }

        $delItems = '';
        if ($deliveries->isEmpty()) {
            $list = [
                ['id' => 'pickup', 'name' => 'Самовывоз'],
                ['id' => 'courier', 'name' => 'Курьер'],
                ['id' => 'yandex', 'name' => 'Яндекс Доставка'],
            ];
        } else {
            $list = $deliveries->map(fn ($d) => [
                'id' => (string) ($d->code ?: $d->id),
                'name' => (string) $d->name,
            ])->all();
        }
        foreach ($list as $d) {
            $id = $e($d['id']);
            $name = $e($d['name']);
            $delItems .= "      <Элемент><Ид>{$id}</Ид><Название>{$name}</Название><Наименование>{$name}</Наименование></Элемент>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.08" ДатаФормирования="{$date}">
  <Справочник>
    <Статусы>
{$statusItems}    </Статусы>
    <Cтатусы>
{$statusItems}    </Cтатусы>
    <ПлатежныеСистемы>
{$payItems}    </ПлатежныеСистемы>
    <СлужбыДоставки>
{$delItems}    </СлужбыДоставки>
    <Доставка>
{$delItems}    </Доставка>
  </Справочник>
  <Статусы>
{$statusItems}  </Статусы>
  <ПлатежныеСистемы>
{$payItems}  </ПлатежныеСистемы>
  <СлужбыДоставки>
{$delItems}  </СлужбыДоставки>
</КоммерческаяИнформация>
XML;
    }
}
