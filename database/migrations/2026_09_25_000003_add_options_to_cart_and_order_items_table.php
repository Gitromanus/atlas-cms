<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Выбранные варианты товара (например, {"Цвет":"Красный","Размер":"M"}),
 * сохраняются в корзине и фиксируются в заказе.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('product_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('options');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};