<?php

namespace App\Services\CommerceML;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            // и вариантные свойства: Ид → ['values' => [идЗначения => имя], 'names' => [список имён]]
            $properties = [];
            $variantProperties = [];
            if (isset($classifier->Свойства)) {
                foreach ($classifier->Свойства->Свойство as $property) {
                    $id = XmlUtils::child($property, 'Ид');
                    if ($id === null) {
                        continue;
                    }

                    $properties[$id] = XmlUtils::child($property, 'Наименование') ?? $id;

                    if (isset($property->ВариантыЗначений)) {
                        $values = [];
                        $names = [];

                        foreach ($property->ВариантыЗначений->children() as $option) {
                            // Стандарт CommerceML: <ВариантЗначения><Ид>…</Ид><Значение>…</Значение>
                            // УТ 1С пользователя:   <Справочник><ИдЗначения>…</ИдЗначения><Значение>…</Значение>
                            $optionId = XmlUtils::child($option, 'Ид') ?? XmlUtils::child($option, 'ИдЗначения');
                            $value = XmlUtils::child($option, 'Значение');

                            if ($value === null || $value === '') {
                                continue;
                            }

                            if ($optionId !== null) {
                                $values[$optionId] = $value;
                            }

                            $names[] = $value;
                        }

                        if ($names !== []) {
                            $variantProperties[$id] = [
                                'values' => $values,
                                'names' => $names,
                            ];
                        }
                    }
                }
            }

            $this->store->set('properties_map', $properties);
            $this->store->set('variant_properties', $variantProperties);
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
        $variantProperties = $this->store->get('variant_properties', []);

        DB::transaction(function () use ($path, &$count, $propertiesMap, $variantProperties) {
            XmlUtils::each($path, 'Товар', function (SimpleXMLElement $item) use (&$count, $propertiesMap, $variantProperties) {
                $this->importProduct($item, $propertiesMap, $variantProperties);
                $count++;
            });
        });

        return $count;
    }

    protected function importProduct(SimpleXMLElement $item, array $propertiesMap, array $variantProperties): void
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

        // Характеристики (вариантные свойства помечаются для выбора на витрине)
        $this->syncFeatures($product, $item, $propertiesMap, $variantProperties);

        // Изображения (внешние ссылки из 1С)
        $this->syncImages($product, $item);
    }

    protected function syncFeatures(Product $product, SimpleXMLElement $item, array $propertiesMap, array $variantProperties): void
    {
        $features = [];

        // Свойства из ЗначенияСвойств (значения могут прийти GUID-ами ВариантыЗначений)
        if (isset($item->ЗначенияСвойств->ЗначенияСвойства)) {
            foreach ($item->ЗначенияСвойств->ЗначенияСвойства as $value) {
                $propId = XmlUtils::child($value, 'Ид');
                $valueText = XmlUtils::child($value, 'Значение');

                if ($propId === null || $valueText === null || $valueText === '') {
                    continue;
                }

                $isVariant = isset($variantProperties[$propId]);
                $resolvedValue = $valueText;

                if ($isVariant) {
                    $valueId = XmlUtils::child($value, 'ИдЗначения') ?? $valueText;
                    $values = $variantProperties[$propId]['values'] ?? [];
                    $names = $variantProperties[$propId]['names'] ?? [];

                    $resolvedValue = $values[$valueId]
                        ?? $values[$valueText]
                        ?? (in_array($valueText, $names, true) ? $valueText : $valueText);
                }

                $features[] = [
                    'name' => $propertiesMap[$propId] ?? $propId,
                    'value' => $resolvedValue,
                    'is_variant' => $isVariant,
                    'options' => $isVariant ? ($variantProperties[$propId]['names'] ?? null) : null,
                ];
            }
        }

        // Характеристики товара (цвет, размер и т.п.) — вариантные свойства для выбора на витрине
        if (isset($item->ХарактеристикиТовара->Характеристика)) {
            foreach ($item->ХарактеристикиТовара->Характеристика as $char) {
                $name = XmlUtils::child($char, 'Наименование') ?? 'Характеристика';

                $names = [];
                if (isset($char->Значения->Значение)) {
                    foreach ($char->Значения->Значение as $charValue) {
                        $v = XmlUtils::child($charValue, 'Значение');
                        if ($v !== null && $v !== '' && ! in_array($v, $names, true)) {
                            $names[] = $v;
                        }
                    }
                }

                $features[] = [
                    'name' => $name,
                    'value' => $names[0] ?? '',
                    'is_variant' => true,
                    'options' => $names,
                ];
            }
        }

        // Удаляем только «каталожные» свойства: вариантные (цвет/размер) приходят из
        // предложений (offers.xml) и должны сохраняться между прогонами каталога.
        $product->features()->where('is_variant', false)->delete();

        foreach ($features as $feature) {
            if ($feature['is_variant']) {
                $existing = $this->findVariantFeature($product, $feature['name']);

                if ($existing === null) {
                    ProductFeature::query()->create([
                        'tenant_id' => $product->tenant_id,
                        'product_id' => $product->id,
                        'name' => $feature['name'],
                        'value' => $feature['value'],
                        'is_variant' => true,
                        'options' => $feature['options'],
                    ]);

                    continue;
                }

                $existing->update([
                    'is_variant' => true,
                    'options' => array_values(array_unique(array_merge(
                        $existing->options ?? [],
                        $feature['options'] ?? []
                    ))),
                ]);

                continue;
            }

            ProductFeature::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'name' => $feature['name'],
                'value' => $feature['value'],
                'is_variant' => false,
                'options' => null,
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

        // Пересоздаём только картинки, пришедшие из 1С (source=1c).
        // Локально загруженные в админке (source=manual) сохраняются.
        $product->images()->where('source', '1c')->delete();

        $startOrder = ((int) $product->images()->max('sort_order')) + 1;

        foreach ($urls as $index => $url) {
            $externalUrl = null;
            $localPath = null;

            // Относительные пути (import_files/...) — сами файлы 1С прислала по протоколу;
            // копируем их в публичное хранилище, чтобы картинки открывались на сайте.
            if (! Str::startsWith($url, ['http://', 'https://'])) {
                $localPath = $this->storeImageFile($url);
            } else {
                $externalUrl = $url;
            }

            ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'url' => $externalUrl,
                'path' => $localPath,
                'source' => '1c',
                'sort_order' => $startOrder + $index,
            ]);
        }
    }

    /**
     * Копирование файла изображения из каталога обмена в публичное хранилище.
     */
    protected function storeImageFile(string $path): ?string
    {
        // Файл может лежать «плоско» (поштучная выгрузка) или в структуре архива
        $source = $this->store->filePath($path);

        if (! File::exists($source)) {
            $source = $this->store->dirPath($path);
        }

        if (! File::exists($source) || ! is_file($source)) {
            return null;
        }

        $slug = app(TenantContext::class)->current()?->slug ?? 'shop';
        $dir = 'products/'.$slug.'/'.now()->format('Y/m');
        $fileName = basename($path);

        File::ensureDirectoryExists(storage_path('app/public/'.$dir));

        if (! File::copy($source, storage_path('app/public/'.$dir.'/'.$fileName))) {
            return null;
        }

        return $dir.'/'.$fileName;
    }

    /**
     * Вариантное свойство товара по имени. «Размер (Одежда)» и «Размер» считаются одним свойством,
     * чтобы характеристики каталога и предложений (в 1С они могут называться по-разному) не дублировались.
     */
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