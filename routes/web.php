<?php

use App\Http\Controllers\Account\AuthController as CustomerAuthController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\OneC\ExchangeController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\DeliveryController;
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

/*
|--------------------------------------------------------------------------
| Один магазин — витрина на корне сайта (без /{slug}/)
|--------------------------------------------------------------------------
*/

Route::prefix('1c')->name('onec.')
    ->controller(ExchangeController::class)
    ->group(function () {
        Route::match(['get', 'post'], '/exchange', 'handle')->name('exchange');
    });

Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
Route::redirect('/login', '/shop/login');

Route::middleware('tenant')->group(function () {
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

    Route::post('/delivery/calculate', [DeliveryController::class, 'calculate'])->name('delivery.calculate');
    Route::get('/delivery/estimate', [DeliveryController::class, 'estimate'])->name('delivery.estimate');
    Route::post('/delivery/estimate', [DeliveryController::class, 'estimate']);
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
});

/*
| Старые URL /odeza-i-obuv/... → /...
| Не трогаем /shop (админка) и /1c
*/
Route::get('/{legacyShop}/{path?}', function (string $legacyShop, ?string $path = null) {
    $reserved = config('atlas.reserved_paths', ['shop', '1c', 'admin', 'api', 'payment', 'livewire', 'storage']);
    if (in_array(strtolower($legacyShop), $reserved, true)) {
        abort(404);
    }
    $target = $path ? '/'.$path : '/';
    $qs = request()->getQueryString();
    if ($qs) {
        $target .= '?'.$qs;
    }

    return redirect()->to($target, 301);
})->where('path', '.*')->where('legacyShop', '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$');
