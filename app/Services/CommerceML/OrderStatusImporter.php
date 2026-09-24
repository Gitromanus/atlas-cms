<?php

namespace App\Services\CommerceML;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * Импорт статусов заказов из orders.xml, присланного 1С (type=sale, mode=import).
 *
 * 1С передаёт заказы в виде документов с реквизитом «СтатусЗаказа».
 */
class OrderStatusImporter
{
    public function __construct(protected ExchangeStore $store) {}

    public function import(string $filename): int
    {
        $path = $this->store->filePath($filename);

        if (! $this->store->hasFile($filename)) {
            throw new \RuntimeException("Файл {$filename} не найден");
        }

        $updated = 0;

        DB::transaction(function () use ($path, &$updated) {
            XmlUtils::each($path, 'Документ', function (SimpleXMLElement $document) use (&$updated) {
                if ($this->applyStatus($document)) {
                    $updated++;
                }
            });
        });

        return $updated;
    }

    protected function applyStatus(SimpleXMLElement $document): bool
    {
        $extId = XmlUtils::child($document, 'Ид');

        if ($extId === null) {
            return false;
        }

        $statusValue = null;

        if (isset($document->ЗначенияРеквизитов->ЗначениеРеквизита)) {
            foreach ($document->ЗначенияРеквизитов->ЗначениеРеквизита as $requisite) {
                if (XmlUtils::child($requisite, 'Наименование') === 'СтатусЗаказа') {
                    $statusValue = XmlUtils::child($requisite, 'Значение');
                    break;
                }
            }
        }

        if ($statusValue === null) {
            return false;
        }

        $order = Order::query()->where('ext_id', $extId)->first();

        if ($order === null) {
            return false;
        }

        $status = OrderStatus::query()
            ->where(fn ($q) => $q->where('ext_code', $statusValue)->orWhere('code', strtolower($statusValue)))
            ->first();

        if ($status !== null) {
            $order->update(['status_id' => $status->id]);

            return true;
        }

        return false;
    }
}