<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Support\DefaultPages;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('View on website')->color('gray')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->url(fn () => $this->record->url(), true),
            Action::make('reset')->label('Reset to default text')->color('gray')
                ->icon('heroicon-m-arrow-uturn-left')
                ->requiresConfirmation()
                ->modalDescription('Replaces the title, description and text with the original template. Your edits on this page will be lost.')
                ->visible(fn () => isset(DefaultPages::all()[$this->record->slug]))
                ->action(function () {
                    [$title, , $meta, $body] = DefaultPages::all()[$this->record->slug];
                    $this->record->update(['title' => $title, 'meta_description' => $meta, 'body' => $body]);
                    $this->fillForm();
                    Notification::make()->success()->title('Default text restored')->send();
                }),
        ];
    }
}
