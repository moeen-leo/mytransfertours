<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

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
