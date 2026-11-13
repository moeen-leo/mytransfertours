<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Admin and staff accounts. Only admins can see or change this screen. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return \Filament\Facades\Filament::auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        // Never let an admin delete their own account and lock everyone out.
        return static::canViewAny() && $record->getKey() !== \Filament\Facades\Filament::auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::Staff)
                    ->required()
                    // An admin cannot demote themselves.
                    ->disabled(fn (?User $record) => $record?->getKey() === \Filament\Facades\Filament::auth()->id()),
                TextInput::make('password')
                    ->password()->revealable()
                    ->rule(\Illuminate\Validation\Rules\Password::min(10))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : 'At least 10 characters.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('last_login_at')->label('Last login')->since()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('role')->options([UserRole::Admin->value => 'Admin', UserRole::Staff->value => 'Staff']),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
