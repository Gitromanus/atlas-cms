<?php

namespace App\Services\CommerceML;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Потоковый импорт каталога CommerceML (import.xml).
 *
 * Обрабатывает классификатор (группы, свойства) и товары.
 * Маппинг записей 1С выполняется по полю ext_id (Ид).
 */
class ImportXmlParser
{
    public function __construct(protected ExchangeStore $store) {}

    public function import(string $filename): int
    {
        $path = $this->store->filePath($filename);

        if (! $this->store->hasFile($filename)) {
            throw new \RuntimeException("Файл {$filename} не найден");
        }

        $this->parseClassifier($path);
        $count = $this->parseProducts($path);

        return $count;
    }

    /**
     * Классификатор: группы (категории) и определения свойств.
     */
    protected function parseClassifier(string $path): void
    {
        XmlUtils::each($path, 'Классификатор', function (SimpleXMLElement $classifier) {
            $this->importGroups($classifier, null);

            // Карта свойств: Ид → Наименование (для значений характеристик товаров)
            $properties = [];
            if (isset($classifier->Свойства)) {
                foreach ($classifier->Свойства->Свойство as $property) {
                    $id = XmlUtils::child($property, 'Ид');
                    if ($id !== null) {
                        $properties[$id] = XmlUtils::child($property, 'Наименование') ?? $id;
                    }
                }
            }

            $this->store->set('properties_map', $properties);
        });
    }

    protected function importGroups(SimpleXMLElement $groups, ?Category $parent): void
    {
        if (! isset($groups->Группы)) {
            return;
        }

        foreach ($groups->Группы->Группа as $group) {
            $extId = XmlUtils::child($group, 'Ид');
            $name = XmlUtils::child($group, 'Наименование') ?? 'Без названия';

            if ($extId === null) {
                continue;
            }

            $category = Category::query()->firstOrNew(['ext_id' => $extId]);
            $category->fill([
                'tenant_id' => app(TenantContext::class)->id(),
                'parent_id' => $parent?->id,
                'name' => $name,
                'is_active' => true,
            ])->save();

            $this->importGroups($group, $category);
        }
    }

    protected function parseProducts(string $path): int
    {
        $count = 0;
        $propertiesMap = $this->store->get('properties_map', []);

        DB::transaction(function () use ($path, &$count, $propertiesMap) {
            XmlUtils::each($path, 'Товар', function (SimpleXMLElement $item) use (&$count, $propertiesMap) {
                $this->importProduct($item, $propertiesMap);
                $count++;
            });
        });

        return $count;
    }

    protected function importProduct(SimpleXMLElement $item, array $propertiesMap): void
    {
        $extId = XmlUtils::child($item, 'Ид');

        if ($extId === null) {
            return;
        }

        $deleted = XmlUtils::bool(XmlUtils::child($item, 'ПометкаУдаления'));

        $product = Product::query()->firstOrNew(['ext_id' => $extId]);

        // Категория из выгрузки
        $categoryId = null;
        $groupId = XmlUtils::ids($item, 'Группы')[0] ?? null;
        if ($groupId !== null) {
            $categoryId = Category::query()->where('ext_id', $groupId)->value('id');
        }

        $product->fill([
            'tenant_id' => app(TenantContext::class)->id(),
            'category_id' => $categoryId ?: $product->category_id,
            'sku' => XmlUtils::child($item, 'Артикул') ?? $product->sku,
            'barcode' => XmlUtils::child($item, 'Штрихкод') ?? $product->barcode,
            'name' => XmlUtils::child($item, 'Наименование') ?? $product->name ?? 'Без названия',
            'description' => XmlUtils::child($item, 'Описание') ?? $product->description,
            'unit' => trim((string) ($item->БазоваяЕдиница['НаименованиеПолное'] ?? '')) ?: $product->unit,
            'is_deleted_from_1c' => $deleted,
            'is_active' => $deleted ? false : true,
        ])->save();

        // Характеристики
        $this->syncFeatures($product, $item, $propertiesMap);

        // Изображения (внешние ссылки из 1С)
        $this->syncImages($product, $item);
    }

    protected function syncFeatures(Product $product, SimpleXMLElement $item, array $propertiesMap): void
    {
        $features = [];

        if (isset($item->ЗначенияСвойств->ЗначенияСвойства)) {
            foreach ($item->ЗначенияСвойств->ЗначенияСвойства as $value) {
                $propId = XmlUtils::child($value, 'Ид');
                $valueText = XmlUtils::child($value, 'Значение');

                if ($propId === null || $valueText === null || $valueText === '') {
                    continue;
                }

                $features[] = [
                    'name' => $propertiesMap[$propId] ?? $propId,
                    'value' => $valueText,
                ];
            }
        }

        $product->features()->delete();

        foreach ($features as $feature) {
            ProductFeature::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'name' => $feature['name'],
                'value' => $feature['value'],
            ]);
        }
    }

    protected function syncImages(Product $product, SimpleXMLElement $item): void
    {
        $urls = [];
        foreach ($item->Картинка as $image) {
            $url = trim((string) $image);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        $product->images()->delete();

        foreach ($urls as $index => $url) {
            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'url' => $url,
                'sort_order' => $index,
            ]);
        }
    }
}