<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Models\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('country_id')
                        ->relationship('country', 'name')
                        ->required()
                        ->default(fn () => \App\Models\Country::where('code', 'MY')->value('id')),
                    TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Select::make('timezone')
                        ->options(collect(\DateTimeZone::listIdentifiers(\DateTimeZone::ASIA))->mapWithKeys(fn ($tz) => [$tz => $tz]))
                        ->searchable()
                        ->default('Asia/Kuala_Lumpur')
                        ->required(),
                    FileUpload::make('image')
                        ->image()->imageEditor()
                        ->disk('uploads')->directory('cities')->visibility('public')
                        ->maxSize(4096)
                        ->columnSpanFull(),
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
            ->modifyQueryUsing(fn ($query) => $query->with('country')->withCount('locations'))
            ->columns([
                ImageColumn::make('image')->disk('uploads')->label('')->imageHeight(40),
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('country.name')->label('Country'),
                TextColumn::make('locations_count')->label('Pick-up points')->alignCenter(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
