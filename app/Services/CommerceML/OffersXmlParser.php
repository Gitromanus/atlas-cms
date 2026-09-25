<?php

namespace App\Services\CommerceML;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * Потоковый импорт предложений CommerceML (offers.xml).
 *
 * Обрабатывает типы цен, склады, цены и остатки.
 */
class OffersXmlParser
{
    public function __construct(protected ExchangeStore $store) {}

    public function import(string $filename): int
    {
        $path = $this->store->filePath($filename);

        if (! $this->store->hasFile($filename)) {
            throw new \RuntimeException("Файл {$filename} не найден");
        }

        $this->parseReferences($path);
        $count = $this->parseOffers($path);

        return $count;
    }

    /**
     * Справочники: типы цен и склады.
     */
    protected function parseReferences(string $path): void
    {
        XmlUtils::each($path, 'ПакетПредложений', function (SimpleXMLElement $package) {
            $tenantId = app(TenantContext::class)->id();

            // Типы цен
            if (isset($package->ТипыЦен->ТипЦены)) {
                foreach ($package->ТипыЦен->ТипЦены as $type) {
                    $extId = XmlUtils::child($type, 'Ид');
                    if ($extId === null) {
                        continue;
                    }

                    PriceType::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'ext_id' => $extId],
                        [
                            'name' => XmlUtils::child($type, 'Наименование') ?? 'Цена',
                            'currency' => XmlUtils::child($type, 'Валюта') ?? 'RUB',
                        ]
                    );
                }
            }

            // Склады
            if (isset($package->Склады->Склад)) {
                foreach ($package->Склады->Склад as $warehouse) {
                    $extId = XmlUtils::child($warehouse, 'Ид');
                    if ($extId === null) {
                        continue;
                    }

                    Warehouse::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'ext_id' => $extId],
                        ['name' => XmlUtils::child($warehouse, 'Наименование') ?? 'Склад']
                    );
                }
            }

            // При выгрузке «только наличие» склады могут не передаваться —
            // создаём склад по умолчанию, чтобы остатки было куда записывать.
            if (Warehouse::query()->where('tenant_id', $tenantId)->doesntExist()) {
                Warehouse::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => 'Основной склад',
                ]);
            }
        });
    }

    protected function parseOffers(string $path): int
    {
        $count = 0;

        DB::transaction(function () use ($path, &$count) {
            XmlUtils::each($path, 'Предложение', function (SimpleXMLElement $offer) use (&$count) {
                $this->importOffer($offer);
                $count++;
            });
        });

        return $count;
    }

    protected function importOffer(SimpleXMLElement $offer): void
    {
        $extId = XmlUtils::child($offer, 'Ид');

        if ($extId === null) {
            return;
        }

        $product = Product::query()->where('ext_id', $extId)->first();

        // У товаров с характеристиками Ид предложения может отличаться от Ид товара.
        // Пробуем сопоставить по артикулу или уникальному наименованию.
        if ($product === null) {
            $tenantId = app(TenantContext::class)->id();

            $sku = XmlUtils::child($offer, 'Артикул');
            if ($sku !== null && $sku !== '') {
                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $sku)
                    ->first();
            }

            if ($product === null) {
                $name = XmlUtils::child($offer, 'Наименование');
                if ($name !== null && $name !== '') {
                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('name', $name)
                        ->first();
                }
            }
        }

        if ($product === null) {
            return;
        }

        $tenantId = $product->tenant_id;

        // Цены
        $priceTypes = PriceType::query()
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'ext_id');

        $offerPrices = [];
        if (isset($offer->Цены->Цена)) {
            foreach ($offer->Цены->Цена as $price) {
                $typeExtId = XmlUtils::child($price, 'ИдТипаЦены');
                $amount = (float) (XmlUtils::child($price, 'ЦенаЗаЕдиницу') ?? 0);

                if ($typeExtId !== null && isset($priceTypes[$typeExtId])) {
                    $offerPrices[$priceTypes[$typeExtId]] = $amount;
                }
            }
        }

        foreach ($offerPrices as $typeId => $amount) {
            ProductPrice::query()->updateOrCreate(
                ['product_id' => $product->id, 'price_type_id' => $typeId],
                ['tenant_id' => $tenantId, 'price' => $amount]
            );
        }

        // Общий остаток (атрибут Количество предложения)
        $quantityTotal = (float) (XmlUtils::child($offer, 'Количество') ?? 0);

        // Остатки по складам: 1С может класть их в Склады или Остатки предложения
        $warehouses = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'ext_id');

        $stockRows = [];

        foreach (['Склады', 'Остатки'] as $container) {
            if (isset($offer->{$container}->Склад)) {
                foreach ($offer->{$container}->Склад as $stock) {
                    $warehouseExtId = XmlUtils::child($stock, 'Ид');
                    $quantity = (float) (XmlUtils::child($stock, 'Количество') ?? 0);

                    if ($warehouseExtId === null || ! isset($warehouses[$warehouseExtId])) {
                        continue;
                    }

                    $stockRows[$warehouses[$warehouseExtId]] = $quantity;
                }
            }

            if ($stockRows !== []) {
                break;
            }
        }

        foreach ($stockRows as $warehouseId => $quantity) {
            ProductStock::query()->updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                ['tenant_id' => $tenantId, 'quantity' => $quantity]
            );
        }

        // Если остатки по складам не указаны — единый остаток на первом складе магазина
        if ($stockRows === []) {
            $warehouseId = $warehouses->first();

            if ($warehouseId !== null) {
                ProductStock::query()->updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                    ['tenant_id' => $tenantId, 'quantity' => $quantityTotal]
                );
            }
        }
    }
}