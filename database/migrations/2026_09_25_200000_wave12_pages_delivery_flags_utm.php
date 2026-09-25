<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_published', 'show_in_menu']);
        });

        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('free_from', 12, 2)->nullable();
            $table->boolean('require_address')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_new')->default(false)->after('is_active');
            $table->boolean('is_hit')->default(false)->after('is_new');
            $table->boolean('is_sale')->default(false)->after('is_hit');
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_method_id')->nullable()->after('delivery_method')->constrained('delivery_methods')->nullOnDelete();
            $table->string('payment_status')->default('pending')->after('is_paid');
            $table->string('payment_id')->nullable()->after('payment_status');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'product_id']);
            $table->index(['tenant_id', 'session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_method_id');
            $table->dropColumn(['payment_status', 'payment_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_new', 'is_hit', 'is_sale', 'meta_title', 'meta_description']);
        });
        Schema::dropIfExists('delivery_methods');
        Schema::dropIfExists('pages');
    }
};
