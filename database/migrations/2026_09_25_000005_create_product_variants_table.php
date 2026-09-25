<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('ext_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->json('options')->nullable()->comment('Комбинация характеристик: {"Размер (Одежда)": "44", "Цвет (Одежда)": "Черный"}');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['product_id', 'ext_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};