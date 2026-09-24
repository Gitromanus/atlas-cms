<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ext_id')->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->unique(['tenant_id', 'ext_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_types');
    }
};