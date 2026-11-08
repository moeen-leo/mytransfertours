<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** `role` is not mass-assignable on the model, so set it explicitly here. */
    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $user = new ($this->getModel())($data);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
