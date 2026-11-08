<?php

namespace App\Filament\Resources\Currencies\Pages;

use App\Filament\Resources\Currencies\CurrencyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateRates')
                ->label('Update rates now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function () {
                    $exit = Artisan::call('app:update-exchange-rates', ['--force' => true]);
                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title($exit === 0 ? 'Rates updated' : 'Could not reach the rate service')
                        ->body($output !== '' ? nl2br(e($output)) : null)
                        ->{$exit === 0 ? 'success' : 'warning'}()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
