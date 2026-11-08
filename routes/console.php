<?php

use Illuminate\Support\Facades\Schedule;

/*
| One hPanel cron job runs `php artisan schedule:run` every minute.
| Everything time-based hangs off it, so no SSH is needed after setup.
*/

// After each Git deploy: run new migrations and refresh caches (only when the build changed).
Schedule::command('app:post-deploy')->everyMinute()->withoutOverlapping();

// Shared hosting has no long-running queue worker: process queued emails each minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Exchange rates (base MYR) from the European Central Bank; the command itself only
// fetches when rates are older than 12 hours, so running it every tick is cheap.
Schedule::command('app:update-exchange-rates')->everyMinute()->withoutOverlapping();
