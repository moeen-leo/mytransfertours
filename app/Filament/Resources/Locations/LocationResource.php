<?php

namespace App\Filament\Resources\Locations;

use App\Enums\LocationType;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Pick-up & drop-off points';

    protected static ?string $modelLabel = 'pick-up / drop-off point';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Where')
                ->columns(2)
                ->schema([
                    Select::make('city_id')
                        ->relationship('city', 'name')
                        ->searchable()->preload()
                        ->required(),
                    Select::make('type')
                        ->options(LocationType::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            $type = $state instanceof LocationType ? $state : LocationType::tryFrom((string) $state);
                            $set('requires_flight_number', $type === LocationType::Airport);
                            $set('requires_address', in_array($type, [LocationType::CityArea, LocationType::HotelZone], true));
                        }),
                    TextInput::make('name')
                        ->placeholder('KL International Airport (KLIA Terminal 1)')
                        ->helperText('What the customer sees in the pick-up / drop-off list.')
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('short_name')
                        ->placeholder('KLIA T1')
                        ->helperText('Used in summaries and emails.'),
                    TextInput::make('iata_code')
                        ->label('Airport code')
                        ->maxLength(3)
                        ->placeholder('KUL')
                        ->visible(function (Get $get) {
                            $type = $get('type');

                            return ($type instanceof LocationType ? $type->value : (string) $type) === LocationType::Airport->value;
                        }),
                    TextInput::make('address')->columnSpanFull(),
                    TextInput::make('lat')->label('Latitude')->numeric(),
                    TextInput::make('lng')->label('Longitude')->numeric(),
                ]),
            Section::make('Booking form')
                ->columns(2)
                ->schema([
                    Toggle::make('requires_flight_number')
                        ->label('Ask for flight number')
                        ->helperText('Turn on for airports.'),
                    Toggle::make('requires_address')
                        ->label('Ask for hotel / address')
                        ->helperText('Turn on for areas like "KL City Centre".'),
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('sort')->numeric()->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->modifyQueryUsing(fn ($query) => $query->with('city'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (Location $r) => $r->short_name),
                TextColumn::make('type')->badge(),
                TextColumn::make('city.name')->label('City')->sortable(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('city_id')->relationship('city', 'name')->label('City'),
                SelectFilter::make('type')->options(LocationType::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
