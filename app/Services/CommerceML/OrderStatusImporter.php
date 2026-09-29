<?php

namespace App\Services\CommerceML;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Импорт статусов заказов из orders.xml (type=sale, mode=import).
 */
class OrderStatusImporter
{
    public function __construct(protected ExchangeStore $store) {}

    public function import(string $filename): int
    {
        $path = $this->resolvePath($filename);

        if ($path === null) {
            throw new \RuntimeException("Файл {$filename} не найден в каталоге обмена");
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

    protected function resolvePath(string $filename): ?string
    {
        foreach ([$this->store->dirPath($filename), $this->store->filePath($filename)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function applyStatus(SimpleXMLElement $document): bool
    {
        $extId = XmlUtils::child($document, 'Ид');
        $number = XmlUtils::child($document, 'Номер');
        $statusValue = $this->extractStatus($document);

        $order = $this->findOrder($extId, $number);

        if ($order === null) {
            Log::info('1C order status: заказ не найден', [
                'ext_id' => $extId,
                'number' => $number,
            ]);

            return false;
        }

        if (filled($extId) && blank($order->ext_id)) {
            $order->ext_id = $extId;
        }

        if ($statusValue === null) {
            $order->save();

            return false;
        }

        $status = OrderStatus::query()
            ->where(function ($q) use ($statusValue) {
                $q->where('ext_code', $statusValue)
                    ->orWhere('code', strtolower($statusValue))
                    ->orWhere('name', $statusValue);
            })
            ->first();

        if ($status === null) {
            $code = $this->makeCode($statusValue);

            $status = OrderStatus::query()->firstOrCreate(
                [
                    'tenant_id' => $order->tenant_id,
                    'code' => $code,
                ],
                [
                    'name' => $statusValue,
                    'ext_code' => $statusValue,
                    'is_system' => false,
                ]
            );
        }

        $order->status_id = $status->id;
        $order->save();

        return true;
    }

    protected function extractStatus(SimpleXMLElement $document): ?string
    {
        if (! isset($document->ЗначенияРеквизитов->ЗначениеРеквизита)) {
            return null;
        }

        foreach ($document->ЗначенияРеквизитов->ЗначениеРеквизита as $requisite) {
            $name = XmlUtils::child($requisite, 'Наименование');

            if (in_array($name, ['СтатусЗаказа', 'Статус заказа', 'Статус'], true)) {
                return XmlUtils::child($requisite, 'Значение');
            }
        }

        return null;
    }

    protected function findOrder(?string $extId, ?string $number): ?Order
    {
        if (filled($extId)) {
            $byExt = Order::query()->where('ext_id', $extId)->first();
            if ($byExt !== null) {
                return $byExt;
            }

            if (ctype_digit($extId)) {
                $byId = Order::query()->find((int) $extId);
                if ($byId !== null) {
                    return $byId;
                }
            }
        }

        if (filled($number)) {
            return Order::query()->where('number', $number)->first();
        }

        return null;
    }

    protected function makeCode(string $value): string
    {
        $s = mb_strtolower(trim($value));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '_', $s) ?: 'status';

        return mb_substr($s, 0, 64);
    }
}
