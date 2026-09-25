<?php

use App\Http\Controllers\Account\AuthController as CustomerAuthController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\OneC\ExchangeController;
use App\Http\Controllers\Platform\LandingController;
use App\Http\Controllers\Platform\ShopRegistrationController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ThemeCssController;
use Illuminate\Support\Facades\Route;

Route::prefix('1c')->name('onec.')
    ->controller(ExchangeController::class)
    ->group(function () {
        Route::match(['get', 'post'], '/exchange', 'handle')->name('exchange');
    });

Route::get('/', [LandingController::class, 'index'])->name('platform.home');

Route::get('/create-shop', [ShopRegistrationController::class, 'create'])->name('platform.register');
Route::post('/create-shop', [ShopRegistrationController::class, 'store']);

Route::redirect('/login', '/shop/login');

Route::get('/_atlas_debug/{slug?}', function (?string $slug = 'futbolka-classic-belaya') {
    try {
        $tenant = \App\Models\Tenant::query()->where('slug', 'odeza-i-obuv')->first();
        app(\App\Services\Tenant\TenantContext::class)->set($tenant);

        $product = \App\Models\Product::query()->where('slug', $slug)->first();
        if (! $product) {
            return response('product not found slug='.$slug, 404);
        }

        $product->load(['images', 'category', 'features', 'variants', 'prices', 'stocks']);

        return response()->json([
            'ok' => true,
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'images' => $product->images->count(),
            'urls' => $product->images->map(fn ($i) => $i->url)->values(),
            'category' => $product->category?->slug,
        ]);
    } catch (\Throwable $e) {
        return response(
            $e::class.': '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString(),
            500,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
});

Route::get('/_atlas_debug_view/{slug?}', function (?string $slug = 'futbolka-classic-belaya') {
    try {
        $tenant = \App\Models\Tenant::query()->where('slug', 'odeza-i-obuv')->first();
        app(\App\Services\Tenant\TenantContext::class)->set($tenant);
        \Illuminate\Support\Facades\URL::defaults(['shop' => 'odeza-i-obuv']);

        $product = \App\Models\Product::query()->where('slug', $slug)->firstOrFail();
        $product->load(['images', 'category', 'features', 'prices']);
        $related = collect();

        return view('shop.product', compact('product', 'related'));
    } catch (\Throwable $e) {
        return response(
            $e::class.': '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString(),
            500,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
});

$shopPattern = '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$';

Route::prefix('{shop}')
    ->where(['shop' => $shopPattern])
    ->middleware('tenant')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/theme.css', ThemeCssController::class)->name('theme.css');

        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/{categorySlug}', [CatalogController::class, 'category'])->name('catalog.category');
        Route::get('/product/{productSlug}', [ProductController::class, 'show'])->name('product.show');

        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
        Route::patch('/cart/{item}/update', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/{item}/remove', [CartController::class, 'remove'])->name('cart.remove');
        Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('/order/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

        Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

        Route::middleware('auth:customers')->prefix('account')->name('account.')->group(function () {
            Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders');
            Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
        });

        Route::prefix('1c')->name('shop.onec.')
            ->controller(ExchangeController::class)
            ->group(function () {
                Route::match(['get', 'post'], '/exchange', 'handle')->name('exchange');
            });
    });
