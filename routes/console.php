<?php

use Illuminate\Support\Facades\Schedule;

/*
| One hPanel cron job runs `php artisan schedule:run` every minute.
| Everything time-based hangs off it, so no SSH is needed after setup.
*/

// Heartbeat, so the launch check can tell the cron job is running.
// (A file, not the cache: post-deploy clears the cache.)
Schedule::call(fn () => @file_put_contents(storage_path('app/schedule-heartbeat'), (string) time()))
    ->everyMinute()->name('heartbeat')->withoutOverlapping();

// Daily database backup at 03:10 Malaysian time, kept for 14 days (see app:backup-database).
Schedule::command('app:backup-database')->dailyAt('03:10')->timezone('Asia/Kuala_Lumpur')->withoutOverlapping();

// After each Git deploy: run new migrations and refresh caches (only when the build changed).
Schedule::command('app:post-deploy')->everyMinute()->withoutOverlapping();

// Shared hosting has no long-running queue worker: process queued emails each minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Exchange rates (base MYR) from the European Central Bank; the command itself only
// fetches once per new ECB publication (weekdays after 16:30 CET), so every tick is cheap.
Schedule::command('app:update-exchange-rates')->everyMinute()->withoutOverlapping();

// Chase suppliers who haven't confirmed (the command decides who is due).
Schedule::command('app:supplier-reminders')->everyMinute()->withoutOverlapping();
