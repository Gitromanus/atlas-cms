<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Нормализованное поле для регистронезависимого поиска по названию и артикулу.
 *
 * SQLite LOWER() не работает с кириллицей, поэтому храним дубликат
 * name + sku в нижнем регистре (mb_strtolower) и ищем по нему.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('search_name')->nullable()->after('name');
            $table->index('search_name');
        });

        // Backfill существующих записей через PHP (не SQL): нижний регистр корректно
        // обрабатывает кириллицу в отличие от SQLite UPPER()/LOWER().
        $rows = DB::table('products')->select('id', 'name', 'sku')->get();

        foreach ($rows as $row) {
            DB::table('products')->where('id', $row->id)->update([
                'search_name' => mb_strtolower(trim(($row->name ?? '').' '.($row->sku ?? ''))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['search_name']);
            $table->dropColumn('search_name');
        });
    }
};