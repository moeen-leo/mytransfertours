<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| One hPanel cron job runs `php artisan schedule:run` every minute.
| Everything time-based hangs off it, so no SSH is needed after setup.
|
| Commands run inside the scheduler's own PHP process (Schedule::call), not as
| separate processes (Schedule::command): Hostinger's PHP has proc_open disabled,
| which Schedule::command needs.
*/

$artisan = fn (string $command, array $args = []) => Schedule::call(fn () => Artisan::call($command, $args))->name($command);

// Heartbeat, so the launch check can tell the cron job is running.
// (A file, not the cache: post-deploy clears the cache.)
Schedule::call(fn () => @file_put_contents(storage_path('app/schedule-heartbeat'), (string) time()))
    ->everyMinute()->name('heartbeat')->withoutOverlapping();

// Daily database backup at 03:10 Malaysian time, kept for 14 days (see app:backup-database).
$artisan('app:backup-database')->dailyAt('03:10')->timezone('Asia/Kuala_Lumpur')->withoutOverlapping();

// After each Git deploy: run new migrations and refresh caches (only when the build changed).
$artisan('app:post-deploy')->everyMinute()->withoutOverlapping();

// Shared hosting has no long-running queue worker: process queued emails each minute.
$artisan('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3])
    ->everyMinute()
    ->withoutOverlapping();

// Exchange rates (base MYR) from the European Central Bank; the command itself only
// fetches once per new ECB publication (weekdays after 16:30 CET), so every tick is cheap.
$artisan('app:update-exchange-rates')->everyMinute()->withoutOverlapping();

// Stripe often hasn't calculated its fee yet when a payment completes: fill it in afterwards (profit uses it).
$artisan('app:sync-stripe-fees')->everyFiveMinutes()->withoutOverlapping();

// Chase suppliers who haven't confirmed (the command decides who is due).
$artisan('app:supplier-reminders')->everyMinute()->withoutOverlapping();
