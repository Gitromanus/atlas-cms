<?php

namespace App\Services\CommerceML;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Формирует orders.xml для выгрузки заказов в 1С (type=sale, mode=query).
 */
class OrdersExporter
{
    public function __construct(protected ExchangeStore $store) {}

    public function export(int $limit = 100): string
    {
        $orders = Order::query()
            ->where('exported_to_1c', false)
            ->with('items')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        // Сохраняем список выданных заказов — отметим их после подтверждения 1С (mode=success)
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

        return $doc->saveXML();
    }

    protected function document(\DOMDocument $doc, Order $order): \DOMElement
    {
        $document = $doc->createElement('Документ');

        $document->appendChild($this->node($doc, 'Ид', $order->ext_id));
        $document->appendChild($this->node($doc, 'Номер', $order->number));
        $document->appendChild($this->node($doc, 'Дата', $order->placed_at?->format('Y-m-d')));
        $document->appendChild($this->node($doc, 'ХозОперация', 'Заказ товара'));
        $document->appendChild($this->node($doc, 'Роль', 'Продавец'));
        $document->appendChild($this->node($doc, 'Валюта', 'руб'));
        $document->appendChild($this->node($doc, 'Курс', '1'));
        $document->appendChild($this->node($doc, 'Сумма', number_format((float) $order->total, 2, '.', '')));

        // Контрагент
        $counteragents = $doc->createElement('Контрагенты');
        $counteragent = $doc->createElement('Контрагент');
        $counteragent->appendChild($this->node($doc, 'Ид', $order->customer_id ? (string) $order->customer_id : (string) $order->ext_id));
        $counteragent->appendChild($this->node($doc, 'Наименование', $order->customer_name));
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

        // Товары
        $goods = $doc->createElement('Товары');

        foreach ($order->items as $item) {
            $good = $doc->createElement('Товар');
            $good->appendChild($this->node($doc, 'Ид', $item->product_id ? (string) $item->product_id : Str::uuid()));
            $good->appendChild($this->node($doc, 'Наименование', $item->product_name));
            $good->appendChild($this->node($doc, 'ЦенаЗаЕдиницу', number_format((float) $item->price, 2, '.', '')));
            $good->appendChild($this->node($doc, 'Количество', (string) $item->quantity));
            $good->appendChild($this->node($doc, 'Сумма', number_format((float) $item->total, 2, '.', '')));
            $goods->appendChild($good);
        }

        $document->appendChild($goods);

        // Реквизиты
        $requisites = $doc->createElement('ЗначенияРеквизитов');

        $requisites->appendChild($this->requisite($doc, 'Метод оплаты', $order->payment_method));
        $requisites->appendChild($this->requisite($doc, 'Способ доставки', $order->delivery_method));
        $requisites->appendChild($this->requisite($doc, 'Адрес доставки', $order->delivery_address));

        if ($order->comment) {
            $requisites->appendChild($this->requisite($doc, 'Комментарий', $order->comment));
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

    protected function requisite(\DOMDocument $doc, string $name, ?string $value): \DOMElement
    {
        $requisite = $doc->createElement('ЗначениеРеквизита');
        $requisite->appendChild($this->node($doc, 'Наименование', $name));
        $requisite->appendChild($this->node($doc, 'Значение', $value));

        return $requisite;
    }
}