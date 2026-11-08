<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    /** Credit settings are not mass-assignable on the model, so set them explicitly here. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        unset($data['role']);
        $record->forceFill($data)->save();

        return $record;
    }
}
