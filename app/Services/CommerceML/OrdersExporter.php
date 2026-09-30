<?php

namespace App\Services\CommerceML;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Str;

/**
 * Формирует orders.xml для выгрузки заказов в 1С (type=sale, mode=query).
 * Также отдаёт справочники статусов/оплаты/доставки — кнопки загрузки в УТ.
 */
class OrdersExporter
{
    public function __construct(protected ExchangeStore $store) {}

    public function export(int $limit = 100): string
    {
        $orders = Order::query()
            ->where('exported_to_1c', false)
            ->with(['items.product'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            if (blank($order->ext_id)) {
                $order->ext_id = (string) Str::uuid();
                $order->save();
            }
        }

        $this->store->set('pending_order_ids', $orders->pluck('id')->all());

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('КоммерческаяИнформация');
        $root->setAttribute('ВерсияСхемы', '2.09');
        $root->setAttribute('ДатаФормирования', now()->format('Y-m-d\TH:i:s'));
        $doc->appendChild($root);

        foreach ($orders as $order) {
            $root->appendChild($this->document($doc, $order));
        }

        $this->appendDictionaries($doc, $root);

        return $doc->saveXML() ?: '<?xml version="1.0" encoding="UTF-8"?><КоммерческаяИнформация/>';
    }

    protected function appendDictionaries(\DOMDocument $doc, \DOMElement $root): void
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

        $addElements = function (\DOMElement $parent, array $items) use ($doc): void {
            foreach ($items as $item) {
                $el = $doc->createElement('Элемент');
                $el->appendChild($this->node($doc, 'Ид', (string) $item['id']));
                $el->appendChild($this->node($doc, 'Название', (string) $item['name']));
                $el->appendChild($this->node($doc, 'Наименование', (string) $item['name']));
                $parent->appendChild($el);
            }
        };

        $statusItems = $statuses->map(fn ($s) => [
            'id' => (string) ($s->ext_code ?: $s->code ?: $s->id),
            'name' => (string) $s->name,
        ])->all();

        $payItems = [
            ['id' => 'cash', 'name' => 'Наличными'],
            ['id' => 'card_online', 'name' => 'Картой онлайн'],
            ['id' => 'card_courier', 'name' => 'Картой курьеру'],
        ];

        if ($deliveries->isEmpty()) {
            $delItems = [
                ['id' => 'pickup', 'name' => 'Самовывоз'],
                ['id' => 'courier', 'name' => 'Курьер'],
                ['id' => 'yandex', 'name' => 'Яндекс Доставка'],
            ];
        } else {
            $delItems = $deliveries->map(fn ($d) => [
                'id' => (string) ($d->code ?: $d->id),
                'name' => (string) $d->name,
            ])->all();
        }

        $spr = $doc->createElement('Справочник');
        foreach (['Статусы', 'Cтатусы'] as $tag) {
            $node = $doc->createElement($tag);
            $addElements($node, $statusItems);
            $spr->appendChild($node);
        }
        $ps = $doc->createElement('ПлатежныеСистемы');
        $addElements($ps, $payItems);
        $spr->appendChild($ps);
        $dl = $doc->createElement('СлужбыДоставки');
        $addElements($dl, $delItems);
        $spr->appendChild($dl);
        $dl2 = $doc->createElement('Доставка');
        $addElements($dl2, $delItems);
        $spr->appendChild($dl2);
        $root->appendChild($spr);

        foreach (['Статусы', 'ПлатежныеСистемы', 'СлужбыДоставки'] as $tag) {
            $node = $doc->createElement($tag);
            $items = match ($tag) {
                'Статусы' => $statusItems,
                'ПлатежныеСистемы' => $payItems,
                default => $delItems,
            };
            $addElements($node, $items);
            $root->appendChild($node);
        }
    }

    protected function document(\DOMDocument $doc, Order $order): \DOMElement
    {
        $document = $doc->createElement('Документ');

        $document->appendChild($this->node($doc, 'Ид', (string) $order->ext_id));
        $document->appendChild($this->node($doc, 'Номер', (string) $order->number));
        $document->appendChild($this->node($doc, 'Дата', $order->placed_at?->format('Y-m-d') ?: now()->format('Y-m-d')));
        $document->appendChild($this->node($doc, 'ХозОперация', 'Заказ товара'));
        $document->appendChild($this->node($doc, 'Роль', 'Продавец'));
        $document->appendChild($this->node($doc, 'Валюта', 'руб'));
        $document->appendChild($this->node($doc, 'Курс', '1'));
        $document->appendChild($this->node($doc, 'Сумма', number_format((float) $order->total, 2, '.', '')));

        $counteragents = $doc->createElement('Контрагенты');
        $counteragent = $doc->createElement('Контрагент');
        $counteragent->appendChild($this->node(
            $doc,
            'Ид',
            $order->customer_id ? 'customer-'.$order->customer_id : 'guest-'.$order->id
        ));
        $counteragent->appendChild($this->node($doc, 'Наименование', $order->customer_name ?: 'Покупатель'));
        $counteragent->appendChild($this->node($doc, 'Роль', 'Покупатель'));

        if ($order->customer_phone) {
            $counteragent->appendChild($this->node($doc, 'Телефон', $order->customer_phone));
        }

        if ($order->delivery_address) {
            $counteragent->appendChild($this->node($doc, 'АдресРегистрации', $order->delivery_address));
        }

        if ($order->customer_email) {
            $contacts = $doc->createElement('Контакты');
            $contact = $doc->createElement('Контакт');
            $contact->appendChild($this->node($doc, 'Тип', 'Почта'));
            $contact->appendChild($this->node($doc, 'Значение', $order->customer_email));
            $contacts->appendChild($contact);
            $counteragent->appendChild($contacts);
        }

        $counteragents->appendChild($counteragent);
        $document->appendChild($counteragents);

        $goods = $doc->createElement('Товары');

        foreach ($order->items as $item) {
            $good = $doc->createElement('Товар');
            $productExtId = $item->product?->ext_id
                ?: ($item->sku ?: null)
                ?: ($item->product?->sku ?: null)
                ?: ($item->product_id ? 'product-'.$item->product_id : 'item-'.$item->id);
            $good->appendChild($this->node($doc, 'Ид', (string) $productExtId));
            $sku = $item->sku ?: $item->product?->sku;
            if ($sku) {
                $good->appendChild($this->node($doc, 'Артикул', (string) $sku));
            }
            $good->appendChild($this->node($doc, 'Наименование', (string) ($item->product_name ?: 'Товар')));
            $good->appendChild($this->node($doc, 'БазоваяЕдиница', (string) ($item->unit ?: $item->product?->unit ?: 'шт')));
            $good->appendChild($this->node($doc, 'ЦенаЗаЕдиницу', number_format((float) $item->price, 2, '.', '')));
            $good->appendChild($this->node($doc, 'Количество', (string) $item->quantity));
            $good->appendChild($this->node($doc, 'Сумма', number_format((float) $item->total, 2, '.', '')));
            $good->appendChild($this->node($doc, 'Единица', (string) ($item->unit ?: $item->product?->unit ?: 'шт')));
            $goods->appendChild($good);
        }

        $document->appendChild($goods);

        $requisites = $doc->createElement('ЗначенияРеквизитов');
        $requisites->appendChild($this->requisite($doc, 'Метод оплаты', (string) ($order->payment_method ?: '')));
        $requisites->appendChild($this->requisite($doc, 'Способ доставки', (string) ($order->delivery_method ?: '')));
        $requisites->appendChild($this->requisite($doc, 'Адрес доставки', (string) ($order->delivery_address ?: '')));

        if ($order->comment) {
            $requisites->appendChild($this->requisite($doc, 'Комментарий', (string) $order->comment));
        }

        $document->appendChild($requisites);

        return $document;
    }

    protected function node(\DOMDocument $doc, string $name, ?string $value): \DOMElement
    {
        $node = $doc->createElement($name);

        if ($value !== null) {
            $node->appendChild($doc->createTextNode($value));
        }

        return $node;
    }

    protected function requisite(\DOMDocument $doc, string $name, string $value): \DOMElement
    {
        $req = $doc->createElement('ЗначениеРеквизита');
        $req->appendChild($this->node($doc, 'Наименование', $name));
        $req->appendChild($this->node($doc, 'Значение', $value));

        return $req;
    }
}
