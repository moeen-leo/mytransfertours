<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Site\AccountController;
use App\Http\Controllers\Site\AuthController;
use App\Http\Controllers\Site\CheckoutController;
use App\Http\Controllers\Site\CurrencyController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\TransferController;
use App\Http\Controllers\Site\TripController;
use Illuminate\Support\Facades\Route;

// ---- Customer website ----
Route::get('/', HomeController::class)->name('home');
Route::post('/currency', CurrencyController::class)->name('currency.set');

Route::get('/transfers', [TransferController::class, 'search'])->name('transfers.search');
Route::get('/transfers/book', [TransferController::class, 'details'])->name('transfers.details');
Route::post('/transfers/book', [TransferController::class, 'add'])->name('transfers.add');

Route::get('/trip', [TripController::class, 'show'])->name('trip');
Route::delete('/trip/items/{item}', [TripController::class, 'remove'])->whereNumber('item')->name('trip.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/booking/{booking}/confirmed', [CheckoutController::class, 'confirmed'])->name('booking.confirmed');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account/bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    // Printable invoice. Staff see any; customers only their own.
    Route::get('/invoices/{booking}', [InvoiceController::class, 'show'])->name('invoices.show');
});

// ---- Plumbing ----
// Uploaded images (vehicles, tours, attractions, cities).
Route::get('/media/{path}', MediaController::class)
    ->where('path', '.*')
    ->name('media')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

// Post-deploy trigger for hosts with hourly-only cron. 404 unless DEPLOY_TOKEN matches.
// (No throttle: it uses the cache table, which doesn't exist before the first migration.)
Route::get('/_deploy/{token}', DeployController::class)
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
