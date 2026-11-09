<?php

namespace App\Filament\Pages;

use Database\Seeders\SampleCatalogueSeeder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

/** The admin home page. Widgets come from app/Filament/Widgets. */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        // Sample data tools: admins only, and never on the live site.
        $canUseSamples = fn (): bool => ! app()->isProduction() && (Filament::auth()->user()?->isAdmin() ?? false);

        return [
            ActionGroup::make([
                Action::make('loadSampleCatalogue')
                    ->label('Load sample catalogue')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->modalHeading('Load the sample catalogue?')
                    ->modalDescription('Adds demo transfers (13 routes × 4 vehicles), 6 tours and 5 attractions in Malaysia with example prices, plus 4 add-ons. Running it again updates them instead of adding copies. Supplier emails for test bookings go to the ADMIN_EMAIL address. Your own products are not touched.')
                    ->modalSubmitActionLabel('Load sample data')
                    ->action(function () {
                        app(SampleCatalogueSeeder::class)->setContainer(app())->run();
                        Notification::make()->success()->title('Sample catalogue loaded')->body('Open the website to try a booking.')->send();
                    }),
                Action::make('hideSampleCatalogue')
                    ->label('Hide sample catalogue')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hide the sample catalogue?')
                    ->modalDescription('Turns off every sample route, tour and attraction so they disappear from the website. Nothing is deleted, and test bookings keep working. You can load them again any time.')
                    ->action(function () {
                        $count = SampleCatalogueSeeder::deactivate();
                        Notification::make()->success()->title('Sample catalogue hidden')->body($count.' products turned off.')->send();
                    }),
            ])
                ->label('Sample data')
                ->icon('heroicon-m-beaker')
                ->button()
                ->color('gray')
                ->visible($canUseSamples),
        ];
    }
}
