<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
    }
}
