<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->foreignId('status_id')->nullable()->constrained('order_statuses')->nullOnDelete();
            $table->string('ext_id')->nullable(); // UUID заказа в 1С для обмена
            $table->decimal('items_total', 12, 2)->default(0);
            $table->decimal('delivery_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('RUB');
            // Снимок данных покупателя на момент заказа
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('delivery_method')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('exported_to_1c')->default(false);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'ext_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};