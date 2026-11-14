<?php

namespace App\Filament\Resources\TransferRoutes\Pages;

use App\Filament\Resources\TransferRoutes\TransferRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransferRoute extends EditRecord
{
    protected static string $resource = TransferRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
