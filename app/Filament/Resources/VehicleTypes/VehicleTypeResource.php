<?php

namespace App\Filament\Resources\VehicleTypes;

use App\Filament\Resources\VehicleTypes\Pages\CreateVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\EditVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\ListVehicleTypes;
use App\Models\VehicleType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
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

class VehicleTypeResource extends Resource
{
    protected static ?string $model = VehicleType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Vehicle')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->placeholder('Standard sedan')
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in links. Filled in automatically.'),
                    TextInput::make('example_models')
                        ->label('Example models')
                        ->placeholder('Toyota Vios or similar'),
                    TextInput::make('description')
                        ->placeholder('Comfortable for your first city miles'),
                    TextInput::make('max_pax')
                        ->label('Max passengers')
                        ->numeric()->minValue(1)->maxValue(60)
                        ->required(),
                    TextInput::make('max_bags')
                        ->label('Max large bags')
                        ->numeric()->minValue(0)->maxValue(60)
                        ->required(),
                    TagsInput::make('features')
                        ->placeholder('Add a feature and press Enter')
                        ->helperText('Shown as ticks on the booking page, e.g. "Meet & greet", "Wi-Fi".')
                        ->columnSpanFull(),
                ]),
            Section::make('Photo')
                ->schema([
                    FileUpload::make('image')
                        ->hiddenLabel()
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:9', '4:3'])
                        ->disk('uploads')
                        ->directory('vehicles')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->helperText('JPG, PNG or WebP, up to 4 MB. A side view on a plain background works best.'),
                ]),
            Section::make('Display')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('sort')->numeric()->default(0)->helperText('Lower shows first.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('image')->disk('uploads')->label('')->imageHeight(44),
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (VehicleType $r) => $r->example_models),
                TextColumn::make('max_pax')->label('Pax')->alignCenter(),
                TextColumn::make('max_bags')->label('Bags')->alignCenter(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleTypes::route('/'),
            'create' => CreateVehicleType::route('/create'),
            'edit' => EditVehicleType::route('/{record}/edit'),
        ];
    }
}
