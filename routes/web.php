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

Route::get('/transfers', [TransferController::class, 'search'])->name('transfers.search');   // landing page when no search yet

Route::get('/tours', [\App\Http\Controllers\Site\TourController::class, 'index'])->name('tours.index');
Route::get('/tours/{tour:slug}', [\App\Http\Controllers\Site\TourController::class, 'show'])->name('tours.show');
Route::get('/tours/{tour:slug}/quote', [\App\Http\Controllers\Site\TourController::class, 'quote'])->middleware('throttle:120,1')->name('tours.quote');
Route::post('/tours/{tour:slug}', [\App\Http\Controllers\Site\TourController::class, 'add'])->name('tours.add');

Route::get('/tickets', [\App\Http\Controllers\Site\AttractionController::class, 'index'])->name('tickets.index');
Route::get('/tickets/{attraction:slug}', [\App\Http\Controllers\Site\AttractionController::class, 'show'])->name('tickets.show');
Route::get('/tickets/{attraction:slug}/quote', [\App\Http\Controllers\Site\AttractionController::class, 'quote'])->middleware('throttle:120,1')->name('tickets.quote');
Route::post('/tickets/{attraction:slug}', [\App\Http\Controllers\Site\AttractionController::class, 'add'])->name('tickets.add');
Route::get('/transfers/book', [TransferController::class, 'details'])->name('transfers.details');
Route::post('/transfers/book', [TransferController::class, 'add'])->name('transfers.add');

Route::get('/trip', [TripController::class, 'show'])->name('trip');
Route::delete('/trip/items/{item}', [TripController::class, 'remove'])->whereNumber('item')->name('trip.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/booking/{booking}/confirmed', [CheckoutController::class, 'confirmed'])->name('booking.confirmed');
Route::get('/checkout/{booking}/paid', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/{booking}/cancelled', [CheckoutController::class, 'cancelled'])->name('checkout.cancelled');

// Stripe calls this after payments (signature-checked; CSRF is excluded in bootstrap/app.php).
Route::post('/stripe/webhook', \App\Http\Controllers\StripeWebhookController::class)->name('stripe.webhook');

// Manage my booking (works for guests): private link from the email, or reference + email.
Route::get('/find-booking', [\App\Http\Controllers\Site\ManageBookingController::class, 'findForm'])->name('manage.findForm');
Route::post('/find-booking', [\App\Http\Controllers\Site\ManageBookingController::class, 'find'])->middleware('throttle:20,1')->name('manage.find');
Route::get('/my-booking/{token}', [\App\Http\Controllers\Site\ManageBookingController::class, 'show'])->name('manage.show');
Route::post('/my-booking/{token}/items/{item}', [\App\Http\Controllers\Site\ManageBookingController::class, 'update'])->whereNumber('item')->middleware('throttle:20,1')->name('manage.update');
Route::post('/my-booking/{token}/change', [\App\Http\Controllers\Site\ManageBookingController::class, 'requestChange'])->middleware('throttle:10,1')->name('manage.change');
Route::get('/my-booking/{token}/invoice', [\App\Http\Controllers\Site\ManageBookingController::class, 'invoice'])->name('manage.invoice');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->middleware('throttle:30,1')->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->middleware('throttle:30,1')->name('auth.google.callback');
});
// Admin "Sign in with Google": only existing Admin/Staff accounts (see AdminGoogleController).
Route::get('/auth/google/admin', [\App\Http\Controllers\AdminGoogleController::class, 'redirect'])->middleware('throttle:30,1')->name('admin.google');
Route::get('/auth/google/admin-callback', [\App\Http\Controllers\AdminGoogleController::class, 'callback'])->middleware('throttle:30,1')->name('admin.google.callback');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:web')->name('logout');

Route::middleware('auth:web')->group(function () {
    Route::get('/account/bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::get('/email/verify', [AuthController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
});

// Printable invoice. Staff (admin login) see any; customers (website login) only their own.
Route::get('/invoices/{booking}', [InvoiceController::class, 'show'])->middleware('auth:admin,web')->name('invoices.show');

// ---- Supplier one-click page (link in supplier emails; token-protected, no login) ----
Route::get('/supplier/{item}/{token}', [\App\Http\Controllers\SupplierBookingController::class, 'show'])->whereNumber('item')->name('supplier.show');
Route::post('/supplier/{item}/{token}/confirm', [\App\Http\Controllers\SupplierBookingController::class, 'confirm'])->whereNumber('item')->middleware('throttle:20,1')->name('supplier.confirm');
Route::post('/supplier/{item}/{token}/decline', [\App\Http\Controllers\SupplierBookingController::class, 'decline'])->whereNumber('item')->middleware('throttle:20,1')->name('supplier.decline');

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
