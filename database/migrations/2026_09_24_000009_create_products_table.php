<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->nullable();
            $table->string('unit')->nullable();
            $table->string('ext_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted_from_1c')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'ext_id']);
            $table->index(['tenant_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};