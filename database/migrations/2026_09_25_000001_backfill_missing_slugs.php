<?php

use App\Support\Slugger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Бэкфилл: заполняет пустые slug товаров и категорий
 * (транслитерация названий, уникальность в пределах магазина).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('products');
        $this->backfill('categories');
    }

    protected function backfill(string $table): void
    {
        DB::table($table)
            ->whereNull('slug')
            ->orWhere('slug', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $base = Slugger::slug((string) $row->name) ?: 'tovar';
                    $slug = $base;
                    $i = 2;

                    while (DB::table($table)
                        ->where('tenant_id', $row->tenant_id)
                        ->where('slug', $slug)
                        ->where('id', '!=', $row->id)
                        ->exists()) {
                        $slug = $base.'-'.$i++;
                    }

                    DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        // Необратимая операция — данные не восстанавливаются
    }
};