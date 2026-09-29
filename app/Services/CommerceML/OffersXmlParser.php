<?php

namespace App\Services\CommerceML;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

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

        return $this->parseOffers($path);
    }

    protected function parseReferences(string $path): void
    {
        XmlUtils::each($path, 'ПакетПредложений', function (SimpleXMLElement $package) {
            $tenantId = app(TenantContext::class)->id();

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

        $tenantId = app(TenantContext::class)->id();
        $baseExtId = $extId;

        if (str_contains($extId, '#')) {
            $baseExtId = explode('#', $extId, 2)[0];
        }

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('ext_id', $baseExtId)
            ->first();

        if ($product === null) {
            $name = XmlUtils::child($offer, 'Наименование');
            if ($name !== null && $name !== '') {
                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();
            }
        }

        if ($product === null) {
            return;
        }

        $tenantId = $product->tenant_id;

        $variantOptions = [];
        if (isset($offer->ХарактеристикиТовара->ХарактеристикаТовара)) {
            foreach ($offer->ХарактеристикиТовара->ХарактеристикаТовара as $char) {
                $charName = trim(XmlUtils::child($char, 'Наименование') ?? '');
                $charValue = trim(XmlUtils::child($char, 'Значение') ?? '');

                if ($charName !== '' && $charValue !== '') {
                    $variantOptions[$charName] = $charValue;
                } elseif ($charName !== '' && $charValue === '') {
                    $variantOptions['Вариант'] = $charName;
                } elseif ($charName === '' && $charValue !== '') {
                    $variantOptions['Вариант'] = $charValue;
                }
            }
        }

        if ($variantOptions === []) {
            $offerName = trim(XmlUtils::child($offer, 'Наименование') ?? '');
            if ($offerName !== '') {
                $variantOptions['Вариант'] = $offerName;
            }
        }

        $this->syncVariantFeatures($product, $offer);

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

        if ($offerPrices === [] && isset($offer->Цены->Цена)) {
            foreach ($offer->Цены->Цена as $price) {
                $amount = (float) (XmlUtils::child($price, 'ЦенаЗаЕдиницу') ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $fallbackType = PriceType::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'ext_id' => 'retail'],
                    ['name' => 'Розничная', 'currency' => 'RUB']
                );
                $offerPrices[$fallbackType->id] = $amount;
                break;
            }
        }

        foreach ($offerPrices as $typeId => $amount) {
            ProductPrice::query()->updateOrCreate(
                ['product_id' => $product->id, 'price_type_id' => $typeId],
                ['tenant_id' => $tenantId, 'price' => $amount]
            );
        }

        $quantityTotal = (float) (XmlUtils::child($offer, 'Количество') ?? 0);

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

        if ($stockRows === [] && $quantityTotal > 0) {
            $warehouseId = $warehouses->first();
            if ($warehouseId === null) {
                $wh = Warehouse::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'ext_id' => 'default'],
                    ['name' => 'Основной']
                );
                $warehouseId = $wh->id;
            }

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

        ProductVariant::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'ext_id' => $extId,
            ],
            [
                'name' => XmlUtils::child($offer, 'Наименование'),
                'options' => $variantOptions,
                'quantity' => $stockRows !== [] ? array_sum($stockRows) : $quantityTotal,
                'price' => $offerPrices !== [] ? max($offerPrices) : null,
            ]
        );
    }

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

            $feature = $this->findVariantFeature($product, $name);

            if ($feature === null) {
                ProductFeature::query()->create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'name' => $name,
                    'value' => $value,
                    'is_variant' => true,
                    'options' => [$value],
                    'sort_order' => ((int) $product->features()->max('sort_order')) + 1,
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

    protected function findVariantFeature(Product $product, string $name): ?ProductFeature
    {
        $feature = ProductFeature::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('name', $name)
            ->where('is_variant', true)
            ->first();

        if ($feature !== null) {
            return $feature;
        }

        $base = $this->baseFeatureName($name);

        return ProductFeature::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('is_variant', true)
            ->get()
            ->first(fn (ProductFeature $existing): bool => $this->baseFeatureName($existing->name) === $base);
    }

    protected function baseFeatureName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s*\(.*\)$/', '', $name)));
    }
}
