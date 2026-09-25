<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('path');
        });

        // Все существующие картинки пришли из 1С — следующий импорт пересоздаст их без дублей
        DB::table('product_images')->update(['source' => '1c']);
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};