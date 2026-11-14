<?php

namespace App\Filament\Resources\PricingRules;

use App\Enums\ProductType;
use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\PricingRules\Pages\ListPricingRules;
use App\Models\PricingRule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Dynamic pricing';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Rule')
                ->description('Raises (or lowers) prices automatically on certain days, dates or times. Base prices stay untouched.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->placeholder('Weekend uplift')->columnSpanFull(),
                    Select::make('product_type')
                        ->label('Applies to')
                        ->options(ProductType::class)
                        ->placeholder('All products'),
                    Select::make('rule_type')
                        ->label('When')
                        ->options([
                            'weekday' => 'On certain weekdays',
                            'date_range' => 'Between two dates (peak season, holidays)',
                            'time_range' => 'At certain times of day (e.g. night surcharge)',
                        ])
                        ->required()
                        ->live(),
                    CheckboxList::make('weekdays')
                        ->options([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'])
                        ->columns(7)
                        ->required()
                        ->visible(fn (Get $get) => $get('rule_type') === 'weekday')
                        ->columnSpanFull(),
                    DatePicker::make('date_from')->native(false)->required()
                        ->visible(fn (Get $get) => $get('rule_type') === 'date_range'),
                    DatePicker::make('date_to')->native(false)->required()->afterOrEqual('date_from')
                        ->visible(fn (Get $get) => $get('rule_type') === 'date_range'),
                    TimePicker::make('time_from')->seconds(false)->required()
                        ->helperText('Can cross midnight, e.g. 23:00 to 06:00.')
                        ->visible(fn (Get $get) => $get('rule_type') === 'time_range'),
                    TimePicker::make('time_to')->seconds(false)->required()
                        ->visible(fn (Get $get) => $get('rule_type') === 'time_range'),
                ]),
            Section::make('Price change')
                ->columns(3)
                ->schema([
                    Select::make('adjustment_type')
                        ->label('Type')
                        ->options(['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount (RM)'])
                        ->required()
                        ->default('percent'),
                    TextInput::make('adjustment_value')
                        ->label('Amount')
                        ->numeric()->required()
                        ->helperText('Use a minus sign to discount, e.g. -10.'),
                    Select::make('applies_to')
                        ->label('Change')
                        ->options(['selling' => 'Selling price only', 'cost' => 'Cost only', 'both' => 'Both'])
                        ->default('selling')->required(),
                    TextInput::make('priority')->numeric()->default(100)
                        ->helperText('When several rules match, lower numbers apply first.'),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('product_type')->label('Applies to')->badge()->placeholder('All products'),
                TextColumn::make('rule_type')->label('When')
                    ->formatStateUsing(fn (string $state) => ['weekday' => 'Weekdays', 'date_range' => 'Dates', 'time_range' => 'Time of day'][$state] ?? $state),
                TextColumn::make('adjustment_value')->label('Change')
                    ->formatStateUsing(fn ($state, PricingRule $r) => ((float) $state > 0 ? '+' : '').rtrim(rtrim(number_format((float) $state, 2), '0'), '.').($r->adjustment_type === 'percent' ? '%' : ' RM')),
                TextColumn::make('priority')->alignCenter(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricingRules::route('/'),
            'create' => CreatePricingRule::route('/create'),
            'edit' => EditPricingRule::route('/{record}/edit'),
        ];
    }
}
