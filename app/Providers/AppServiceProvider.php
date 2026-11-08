<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "uploads" disk for images added in the admin (vehicles, tours, attractions, cities).
        // On Hostinger it points outside the deployed folder so Git redeploys never wipe it;
        // files are served through the /media route (routes/web.php).
        config(['filesystems.disks.uploads' => [
            'driver' => 'local',
            'root' => $_SERVER['MTT_UPLOADS_PATH'] ?? storage_path('app/uploads'),
            'url' => rtrim((string) config('app.url'), '/').'/media',
            'visibility' => 'public',
            'throw' => false,
        ]]);
    }

    public function boot(): void
    {
        // Short, stable names in polymorphic columns, so renaming a class never breaks old bookings.
        Relation::enforceMorphMap([
            'transfer_route' => \App\Models\TransferRoute::class,
            'tour' => \App\Models\Tour::class,
            'attraction' => \App\Models\Attraction::class,
        ]);

        // Catch N+1 queries and typos in attribute names while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Header data for every website page: currency switcher and the "My trip" count.
        View::composer(['layouts.site', 'site.*'], function ($view) {
            $view->with([
                'siteCurrencies' => collect(\App\Models\Currency::activeMap())->values(),
                'siteCurrency' => \App\Support\Money::current(),
                'cartCount' => app(\App\Services\CartService::class)->count(),
            ]);
        });

        // Customer confirmed their email: their earlier guest bookings appear under "My bookings".
        Event::listen(\Illuminate\Auth\Events\Verified::class, function ($event) {
            if ($event->user instanceof \App\Models\User) {
                $event->user->claimGuestBookings();
            }
        });

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof \App\Models\User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
