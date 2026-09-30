<?php

namespace App\Services\CommerceML;

use App\Models\DeliveryMethod;
use App\Models\OrderStatus;

/**
 * Справочники для 1С (кнопки «Загрузить статусы / службы доставки / платёжные системы»).
 * type=sale&mode=info
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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Справочник>'."\n";

        foreach (['Статусы', 'Cтатусы'] as $tag) {
            $xml .= "  <{$tag}>\n";
            foreach ($statuses as $s) {
                $id = $e((string) ($s->ext_code ?: $s->code ?: $s->id));
                $name = $e((string) $s->name);
                $xml .= "    <Элемент><Ид>{$id}</Ид><Название>{$name}</Название></Элемент>\n";
            }
            $xml .= "  </{$tag}>\n";
        }

        $xml .= "  <ПлатежныеСистемы>\n";
        foreach ([
            ['id' => 'cash', 'name' => 'Наличными'],
            ['id' => 'card_online', 'name' => 'Картой онлайн'],
            ['id' => 'card_courier', 'name' => 'Картой курьеру'],
        ] as $ps) {
            $xml .= '    <Элемент><Ид>'.$e($ps['id']).'</Ид><Название>'.$e($ps['name']).'</Название></Элемент>'."\n";
        }
        $xml .= "  </ПлатежныеСистемы>\n";

        $xml .= "  <СлужбыДоставки>\n";
        if ($deliveries->isEmpty()) {
            foreach ([
                ['id' => 'pickup', 'name' => 'Самовывоз'],
                ['id' => 'courier', 'name' => 'Курьер'],
                ['id' => 'yandex', 'name' => 'Яндекс Доставка'],
            ] as $d) {
                $xml .= '    <Элемент><Ид>'.$e($d['id']).'</Ид><Название>'.$e($d['name']).'</Название></Элемент>'."\n";
            }
        } else {
            foreach ($deliveries as $d) {
                $xml .= '    <Элемент><Ид>'.$e((string) ($d->code ?: $d->id)).'</Ид><Название>'.$e((string) $d->name).'</Название></Элемент>'."\n";
            }
        }
        $xml .= "  </СлужбыДоставки>\n";
        $xml .= '</Справочник>';

        return $xml;
    }
}
