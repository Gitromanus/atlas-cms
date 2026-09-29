<?php

namespace App\Console\Commands;

use App\Models\ProductFeature;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

/**
 * Сбрасывает is_variant у свойств номенклатуры (Сезон, Пол и т.п.).
 * Оставляет is_variant=true только у имён из options ProductVariant (offers / характеристики 1С).
 */
class FixProductVariantFlags extends Command
{
    protected $signature = 'atlas:fix-variant-flags {--tenant=}';

    protected $description = 'Варианты только из характеристик 1С (offers), не из Дополнительно';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $variantOptionNames = [];
        $q = ProductVariant::query()->whereNotNull('options');
        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        foreach ($q->cursor() as $variant) {
            foreach (array_keys((array) $variant->options) as $name) {
                $variantOptionNames[$name] = true;
            }
        }

        $this->info('Имён опций из variants: '.count($variantOptionNames));
        if ($variantOptionNames !== []) {
            $this->line(implode(', ', array_keys($variantOptionNames)));
        }

        $features = ProductFeature::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_variant', true)
            ->get();

        $demoted = 0;
        foreach ($features as $feature) {
            if (! isset($variantOptionNames[$feature->name])) {
                $feature->update(['is_variant' => false, 'options' => null]);
                $demoted++;
            }
        }

        $this->info("Снято is_variant: {$demoted}");

        $promoted = 0;
        if ($variantOptionNames !== []) {
            $promoted = ProductFeature::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('is_variant', false)
                ->whereIn('name', array_keys($variantOptionNames))
                ->update(['is_variant' => true]);
        }

        $this->info("Выставлено is_variant: {$promoted}");

        return self::SUCCESS;
    }
}
