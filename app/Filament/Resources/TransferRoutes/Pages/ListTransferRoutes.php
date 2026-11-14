<?php

namespace App\Filament\Resources\TransferRoutes\Pages;

use App\Filament\Resources\TransferRoutes\TransferRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferRoutes extends ListRecords
{
    protected static string $resource = TransferRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
