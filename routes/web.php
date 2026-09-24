<?php

use App\Http\Controllers\Account\AuthController as CustomerAuthController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\OneC\ExchangeController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ThemeCssController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Обмен с 1С по протоколу CommerceML (без CSRF, Basic-авторизация)
// ---------------------------------------------------------------------------
Route::prefix('1c')->name('onec.')
    ->controller(ExchangeController::class)
    ->group(function () {
        Route::match(['get', 'post'], '/exchange', 'handle');
    });

// ---------------------------------------------------------------------------
// Витрина магазина (требуется активный тенант)
// ---------------------------------------------------------------------------
Route::middleware('tenant')->group(function () {
    Route::get('/theme.css', ThemeCssController::class)->name('theme.css');

    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{category:slug}', [CatalogController::class, 'category'])->name('catalog.category');
    Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');

    // Корзина
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{item}/update', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{item}/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

    // Оформление заказа
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/order/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

    // Авторизация покупателя
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('/login', [CustomerAuthController::class, 'login']);
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

    // Личный кабинет покупателя
    Route::middleware('auth:customers')->prefix('account')->name('account.')->group(function () {
        Route::get('/orders', [AccountOrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    });
});
