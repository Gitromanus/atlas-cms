<?php

use App\Http\Controllers\Account\AuthController as CustomerAuthController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\OneC\ExchangeController;
use App\Http\Controllers\Platform\LandingController;
use App\Http\Controllers\Platform\ShopRegistrationController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CompareController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderTrackController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\PostController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\SitemapController;
use App\Http\Controllers\Shop\ThemeCssController;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('1c')->name('onec.')
    ->controller(ExchangeController::class)
    ->group(function () {
        Route::match(['get', 'post'], '/exchange', 'handle')->name('exchange');
    });

Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');

Route::get('/', [LandingController::class, 'index'])->name('platform.home');
Route::get('/create-shop', [ShopRegistrationController::class, 'create'])->name('platform.register');
Route::post('/create-shop', [ShopRegistrationController::class, 'store']);
Route::redirect('/login', '/shop/login');

$shopPattern = '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$';

Route::prefix('{shop}')
    ->where(['shop' => $shopPattern])
    ->middleware('tenant')
    ->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');
        Route::get('/theme.css', ThemeCssController::class)->name('theme.css');
        Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/{categorySlug}', [CatalogController::class, 'category'])->name('catalog.category');
        Route::get('/product/{productSlug}', [ProductController::class, 'show'])->name('product.show');
        Route::post('/product/{productSlug}/review', [ReviewController::class, 'store'])->name('product.review');

        Route::get('/page/{pageSlug}', [PageController::class, 'show'])->name('page.show');

        Route::get('/articles', [PostController::class, 'articles'])->name('articles.index');
        Route::get('/news', [PostController::class, 'news'])->name('news.index');
        Route::get('/blog/{postSlug}', [PostController::class, 'show'])->name('posts.show');

        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
        Route::patch('/cart/{item}/update', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/{item}/remove', [CartController::class, 'remove'])->name('cart.remove');
        Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
        Route::post('/compare/toggle', [CompareController::class, 'toggle'])->name('compare.toggle');
        Route::post('/compare/clear', [CompareController::class, 'clear'])->name('compare.clear');

        Route::get('/track', [OrderTrackController::class, 'form'])->name('order.track');
        Route::post('/track', [OrderTrackController::class, 'lookup'])->name('order.track.lookup');

        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('/order/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
        Route::get('/payment/{order}', [PaymentController::class, 'pay'])->name('payment.pay');

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
