<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'price_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};