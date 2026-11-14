<?php

namespace App\Filament\Resources\TransferRoutes;

use App\Filament\Resources\TransferRoutes\Pages\CreateTransferRoute;
use App\Filament\Resources\TransferRoutes\Pages\EditTransferRoute;
use App\Filament\Resources\TransferRoutes\Pages\ListTransferRoutes;
use App\Models\Location;
use App\Models\TransferRoute;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
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

class TransferRouteResource extends Resource
{
    protected static ?string $model = TransferRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Transfers';

    protected static ?string $modelLabel = 'transfer route';

    public static function locationOptions(): array
    {
        return Location::query()
            ->with('city')
            ->orderBy('city_id')->orderBy('sort')->orderBy('name')
            ->get()
            ->groupBy(fn (Location $l) => $l->city?->name ?? 'Other')
            ->map(fn ($group) => $group->mapWithKeys(fn (Location $l) => [$l->id => $l->name])->all())
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Route')
                ->columns(2)
                ->schema([
                    Select::make('from_location_id')
                        ->label('Pick-up')
                        ->options(fn () => static::locationOptions())
                        ->searchable()
                        ->required(),
                    Select::make('to_location_id')
                        ->label('Drop-off')
                        ->options(fn () => static::locationOptions())
                        ->searchable()
                        ->required()
                        ->different('from_location_id')
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule, Get $get) => $rule->where('from_location_id', $get('from_location_id')),
                        )
                        ->validationMessages(['unique' => 'This route already exists. Edit it instead.']),
                    Toggle::make('is_bidirectional')
                        ->label('Same price in both directions')
                        ->helperText('KLIA → KL City also sells as KL City → KLIA.')
                        ->default(true)
                        ->columnSpanFull(),
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->relationship('supplier', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()->preload()
                        ->required()
                        ->helperText('Gets the booking email. Can be overridden per vehicle below.'),
                    TextInput::make('distance_km')->label('Distance')->numeric()->suffix('km'),
                    TextInput::make('duration_minutes')->label('Typical duration')->numeric()->suffix('min'),
                    TextInput::make('free_waiting_minutes')->label('Free waiting')->numeric()->suffix('min')->default(60),
                    TextInput::make('cancellation_hours')
                        ->label('Free cancellation until')
                        ->numeric()->suffix('hours before')->default(24),
                    Toggle::make('is_active')->label('Active (bookable)')->default(true),
                ]),
            Section::make('Prices per vehicle')
                ->description('Price is for the whole vehicle, not per person. Customers only ever see the selling price; suppliers only the cost.')
                ->schema([
                    Repeater::make('prices')
                        ->hiddenLabel()
                        ->relationship()
                        ->table([
                            TableColumn::make('Vehicle')->markAsRequired()->width('190px'),
                            TableColumn::make('Cost (RM)')->markAsRequired(),
                            TableColumn::make('Selling (RM)')->markAsRequired(),
                            TableColumn::make('Margin')->width('130px'),
                            TableColumn::make('Other supplier'),
                            TableColumn::make('Valid from'),
                            TableColumn::make('Valid to'),
                            TableColumn::make('On')->width('60px'),
                        ])
                        ->schema([
                            Select::make('vehicle_type_id')
                                ->relationship('vehicleType', 'name')
                                ->required(),
                            TextInput::make('cost_price')->numeric()->minValue(0)->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set('margin', static::margin($get))),
                            TextInput::make('selling_price')->numeric()->minValue(0)->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set) => $set('margin', static::margin($get)))
                                ->gte('cost_price'),
                            TextInput::make('margin')
                                ->disabled()->dehydrated(false)
                                ->afterStateHydrated(fn (TextInput $component, Get $get) => $component->state(static::margin($get))),
                            Select::make('supplier_id')
                                ->relationship('supplier', 'name')
                                ->placeholder('Route supplier'),
                            DatePicker::make('valid_from')->native(false),
                            DatePicker::make('valid_to')->native(false)->afterOrEqual('valid_from'),
                            Toggle::make('is_active')->default(true),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Add vehicle price')
                        ->reorderable(false),
                ]),
        ]);
    }

    private static function margin(Get $get): string
    {
        $cost = (float) $get('cost_price');
        $sell = (float) $get('selling_price');

        if ($sell <= 0) {
            return '—';
        }

        return number_format($sell - $cost, 2).' ('.round(($sell - $cost) / $sell * 100).'%)';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['from.city', 'to', 'supplier'])->withCount('prices')->withMin('prices', 'selling_price'))
            ->columns([
                TextColumn::make('from.name')->label('Pick-up')->searchable()->weight('semibold')
                    ->description(fn (TransferRoute $r) => $r->from?->city?->name),
                TextColumn::make('to.name')->label('Drop-off')->searchable()
                    ->description(fn (TransferRoute $r) => $r->is_bidirectional ? '↔ both ways' : '→ one way'),
                TextColumn::make('supplier.name')->label('Supplier'),
                TextColumn::make('prices_count')->label('Vehicles')->alignCenter(),
                TextColumn::make('prices_min_selling_price')->label('From')->money('MYR'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->relationship('supplier', 'name')->label('Supplier'),
                SelectFilter::make('from_location_id')->relationship('from', 'name')->label('Pick-up')->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferRoutes::route('/'),
            'create' => CreateTransferRoute::route('/create'),
            'edit' => EditTransferRoute::route('/{record}/edit'),
        ];
    }
}
