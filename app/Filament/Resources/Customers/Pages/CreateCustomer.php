<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /** Credit settings are not mass-assignable on the model, so set everything explicitly. */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->forceFill($data);
        $user->password ??= Str::password(24);   // they can reset it themselves later
        $user->role = UserRole::Customer;
        $user->save();

        return $user;
    }
}
