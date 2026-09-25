<?php

namespace App\Services\CommerceML;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
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

        // У вариантов Ид предложения имеет вид «{ИдТовара}#{ИдВарианта}» — сопоставляем по базовой части
        $baseExtId = strtok($extId, '#');

        $product = Product::query()->where('ext_id', $baseExtId)->first();

        // Если Ид не совпал — пробуем сопоставить по артикулу или уникальному наименованию.
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

        // Вариантные характеристики (цвет, размер) из предложений
        $this->syncVariantFeatures($product, $offer);

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

        // Если остатки по складам не указаны — единый остаток на первом складе магазина.
        // У вариантов (Ид «{товар}#{вариант}») остатки складываются в общий остаток товара.
        if ($stockRows === []) {
            $warehouseId = $warehouses->first();

            if ($warehouseId !== null) {
                $stock = ProductStock::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock === null) {
                    ProductStock::query()->create([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $quantityTotal,
                    ]);
                } elseif ($baseExtId !== $extId) {
                    $stock->increment('quantity', $quantityTotal);
                } else {
                    $stock->update(['quantity' => $quantityTotal]);
                }
            }
        }
    }

    /**
     * Характеристики предложений (цвет, размер и т.п.) → вариантные свойства товара.
     *
     * Значения из всех предложений товара объединяются в options для выбора на витрине.
     */
    protected function syncVariantFeatures(Product $product, SimpleXMLElement $offer): void
    {
        if (! isset($offer->ХарактеристикиТовара->ХарактеристикаТовара)) {
            return;
        }

        foreach ($offer->ХарактеристикиТовара->ХарактеристикаТовара as $char) {
            $name = trim(XmlUtils::child($char, 'Наименование') ?? '');
            $value = trim(XmlUtils::child($char, 'Значение') ?? '');

            if ($name === '' || $value === '') {
                continue;
            }

            $feature = ProductFeature::query()
                ->where('tenant_id', $product->tenant_id)
                ->where('product_id', $product->id)
                ->where('name', $name)
                ->first();

            if ($feature === null) {
                ProductFeature::query()->create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'name' => $name,
                    'value' => $value,
                    'is_variant' => true,
                    'options' => [$value],
                ]);

                continue;
            }

            $options = $feature->options ?? [];
            if (! in_array($value, $options, true)) {
                $options[] = $value;
            }

            $feature->update([
                'is_variant' => true,
                'options' => $options,
            ]);
        }
    }
}