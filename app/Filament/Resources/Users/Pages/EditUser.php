<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** `role` is not mass-assignable on the model, so set it explicitly here. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('role', $data) && $record->getKey() !== \Filament\Facades\Filament::auth()->id()) {
            $record->role = $data['role'];
        }
        unset($data['role']);

        $record->fill($data)->save();

        return $record;
    }
}
