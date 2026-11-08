<?php

namespace App\Filament\Resources\Tours;

use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\Tour;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Details')->schema([
                        Section::make()->columns(2)->schema([
                            TextInput::make('title')
                                ->required()->maxLength(160)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null)
                                ->columnSpanFull(),
                            TextInput::make('slug')->required()->unique(ignoreRecord: true),
                            Select::make('city_id')->relationship('city', 'name')->required()->preload(),
                            Select::make('supplier_id')
                                ->relationship('supplier', 'name', fn ($query) => $query->where('is_active', true))
                                ->searchable()->preload()->required()
                                ->helperText('Gets the booking email.'),
                            TextInput::make('duration_minutes')
                                ->label('Duration')->numeric()->suffix('minutes')->required()
                                ->helperText('e.g. 300 for 5 hours'),
                            Textarea::make('summary')
                                ->rows(2)->maxLength(500)
                                ->helperText('One or two sentences shown on cards and search results.')
                                ->columnSpanFull(),
                            MarkdownEditor::make('description')->columnSpanFull(),
                        ]),
                        Section::make('Photos')->schema([
                            FileUpload::make('images')
                                ->hiddenLabel()
                                ->multiple()->reorderable()->maxFiles(10)
                                ->image()->imageEditor()
                                ->disk('uploads')->directory('tours')->visibility('public')
                                ->maxSize(4096)
                                ->panelLayout('grid')
                                ->helperText('Up to 10 photos, 4 MB each. The first one is the cover; drag to reorder.'),
                        ]),
                    ]),

                    Tab::make('Itinerary')->schema([
                        Repeater::make('itinerary')
                            ->table([
                                TableColumn::make('Time'),
                                TableColumn::make('What happens')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('time')->placeholder('08:30'),
                                TextInput::make('text')->required()->placeholder('Pick-up from your hotel'),
                            ])
                            ->addActionLabel('Add stop'),
                        TagsInput::make('highlights')->placeholder('Add a highlight and press Enter'),
                        TagsInput::make('inclusions')->label("What's included")->placeholder('e.g. Hotel pick-up'),
                        TagsInput::make('exclusions')->label('Not included')->placeholder('e.g. Meals'),
                    ]),

                    Tab::make('Booking & prices')->schema([
                        Section::make('Seat-in-coach (shared)')
                            ->description('Joins a group. Priced per person.')
                            ->schema([
                                Toggle::make('has_sic')->label('Offer seat-in-coach')->default(true)->live(),
                                TagsInput::make('sic_departure_times')
                                    ->label('Departure times')
                                    ->placeholder('08:30')
                                    ->nestedRecursiveRules(['date_format:H:i'])
                                    ->helperText('24-hour format, press Enter after each.')
                                    ->visible(fn (Get $get) => (bool) $get('has_sic')),
                                Repeater::make('sicPrices')
                                    ->label('Prices per person')
                                    ->relationship()
                                    ->visible(fn (Get $get) => (bool) $get('has_sic'))
                                    ->table([
                                        TableColumn::make('Passenger')->markAsRequired(),
                                        TableColumn::make('Cost (RM)')->markAsRequired(),
                                        TableColumn::make('Selling (RM)')->markAsRequired(),
                                        TableColumn::make('Valid from'),
                                        TableColumn::make('Valid to'),
                                    ])
                                    ->schema([
                                        Select::make('pax_type')->options(['adult' => 'Adult', 'child' => 'Child'])->required(),
                                        TextInput::make('cost_price')->numeric()->minValue(0)->required(),
                                        TextInput::make('selling_price')->numeric()->minValue(0)->required()->gte('cost_price'),
                                        DatePicker::make('valid_from')->native(false),
                                        DatePicker::make('valid_to')->native(false),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add price'),
                            ]),
                        Section::make('Private')
                            ->description('Own vehicle and guide. Priced per vehicle.')
                            ->schema([
                                Toggle::make('has_private')->label('Offer private')->default(true)->live(),
                                TagsInput::make('private_start_times')
                                    ->label('Start times customers can pick')
                                    ->placeholder('08:00')
                                    ->nestedRecursiveRules(['date_format:H:i'])
                                    ->visible(fn (Get $get) => (bool) $get('has_private')),
                                Repeater::make('privatePrices')
                                    ->label('Prices per vehicle')
                                    ->relationship()
                                    ->visible(fn (Get $get) => (bool) $get('has_private'))
                                    ->table([
                                        TableColumn::make('Vehicle')->markAsRequired(),
                                        TableColumn::make('Cost (RM)')->markAsRequired(),
                                        TableColumn::make('Selling (RM)')->markAsRequired(),
                                        TableColumn::make('Valid from'),
                                        TableColumn::make('Valid to'),
                                    ])
                                    ->schema([
                                        Select::make('vehicle_type_id')->relationship('vehicleType', 'name')->required(),
                                        TextInput::make('cost_price')->numeric()->minValue(0)->required(),
                                        TextInput::make('selling_price')->numeric()->minValue(0)->required()->gte('cost_price'),
                                        DatePicker::make('valid_from')->native(false),
                                        DatePicker::make('valid_to')->native(false),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add vehicle price'),
                            ]),
                        Section::make('Rules')->columns(4)->schema([
                            TextInput::make('child_age_from')->label('Child age from')->numeric()->default(3),
                            TextInput::make('child_age_to')->label('Child age to')->numeric()->default(11),
                            TextInput::make('min_pax')->label('Minimum people')->numeric()->default(1),
                            TextInput::make('cancellation_hours')->label('Free cancellation')->numeric()->suffix('h before')->default(24),
                        ]),
                    ]),
                ]),
            Section::make()->columns(2)->columnSpanFull()->schema([
                Toggle::make('is_active')->label('Active (bookable)')->default(true),
                TextInput::make('sort')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->modifyQueryUsing(fn ($query) => $query->with(['city', 'supplier']))
            ->columns([
                ImageColumn::make('images')->disk('uploads')->label('')->imageHeight(44)->limit(1),
                TextColumn::make('title')->searchable()->weight('semibold')->wrap()
                    ->description(fn (Tour $r) => $r->city?->name),
                TextColumn::make('options')
                    ->label('Options')
                    ->state(fn (Tour $r) => array_values(array_filter([$r->has_sic ? 'Seat-in-coach' : null, $r->has_private ? 'Private' : null])))
                    ->badge(),
                TextColumn::make('duration_minutes')->label('Duration')
                    ->formatStateUsing(fn ($state) => intdiv((int) $state, 60).'h'.((int) $state % 60 ? ' '.((int) $state % 60).'m' : '')),
                TextColumn::make('supplier.name')->label('Supplier')->toggleable(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('city_id')->relationship('city', 'name')->label('City'),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), RestoreAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'edit' => EditTour::route('/{record}/edit'),
        ];
    }
}
