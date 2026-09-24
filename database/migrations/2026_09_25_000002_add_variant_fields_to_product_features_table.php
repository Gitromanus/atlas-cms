<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Вариантные свойства товаров (цвет, размер и т.п.):
 *  - is_variant — свойство участвует в выборе варианта на витрине;
 *  - options    — допустимые значения (ВариантыЗначений из классификатора 1С).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_features', function (Blueprint $table) {
            $table->boolean('is_variant')->default(false)->after('value');
            $table->json('options')->nullable()->after('is_variant');
        });
    }

    public function down(): void
    {
        Schema::table('product_features', function (Blueprint $table) {
            $table->dropColumn(['is_variant', 'options']);
        });
    }
};