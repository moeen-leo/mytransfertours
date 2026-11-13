<?php

namespace App\Filament\Resources\Currencies;

use App\Filament\Resources\Currencies\Pages\CreateCurrency;
use App\Filament\Resources\Currencies\Pages\EditCurrency;
use App\Filament\Resources\Currencies\Pages\ListCurrencies;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'code';

    public static function canDelete(Model $record): bool
    {
        return ! $record->is_base;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->description('You enter all prices in RM. Customers paying in another currency see RM price × rate × (1 + buffer).')
                ->columns(3)
                ->schema([
                    TextInput::make('code')
                        ->required()->length(3)->unique(ignoreRecord: true)
                        ->placeholder('USD')
                        ->dehydrateStateUsing(fn (?string $state) => strtoupper((string) $state))
                        ->disabled(fn (?Currency $record) => (bool) $record?->is_base),
                    TextInput::make('name')->required()->placeholder('US dollar'),
                    TextInput::make('symbol')->required()->maxLength(8)->placeholder('$'),
                    Toggle::make('auto_update')
                        ->label('Update the rate automatically every day')
                        ->default(true)
                        ->live()
                        ->hidden(fn (?Currency $record) => (bool) $record?->is_base)
                        ->columnSpanFull(),
                    TextInput::make('rate')
                        ->label('1 RM equals')
                        ->numeric()->minValue(0.000001)->required()
                        ->live(onBlur: true)
                        ->suffix(fn (Get $get) => strtoupper((string) $get('code')) ?: '…')
                        ->disabled(fn (?Currency $record, Get $get) => (bool) $record?->is_base || (bool) $get('auto_update'))
                        ->dehydrated()
                        ->helperText(fn (Get $get) => $get('auto_update')
                            ? 'Updated automatically every day. Turn off the switch above to set it yourself.'
                            : 'Set by hand. Check a current rate, e.g. on Google: "1 MYR to USD".'),
                    TextInput::make('buffer_percent')
                        ->label('Buffer')
                        ->numeric()->minValue(0)->maxValue(20)->default(2)
                        ->live(onBlur: true)
                        ->suffix('%')
                        ->disabled(fn (?Currency $record) => (bool) $record?->is_base)
                        ->helperText('Recommended 3%: covers card currency-conversion fees and daily rate swings.'),
                    TextInput::make('sort')->numeric()->default(0),
                    Text::make(function (Get $get) {
                        $rate = (float) $get('rate') * (1 + (float) $get('buffer_percent') / 100);

                        return $rate > 0
                            ? 'Example: a RM 100.00 transfer shows as '.($get('symbol') ?: '').' '.number_format(100 * $rate, 2).' to the customer.'
                            : '';
                    })->columnSpanFull(),
                    Toggle::make('is_active')->label('Offered on the website')->default(true)
                        ->disabled(fn (?Currency $record) => (bool) $record?->is_base),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('code')->weight('semibold')
                    ->description(fn (Currency $r) => $r->name),
                TextColumn::make('rate')->label('1 RM =')
                    ->formatStateUsing(fn ($state, Currency $r) => rtrim(rtrim(number_format((float) $state, 6), '0'), '.').' '.$r->code),
                TextColumn::make('buffer_percent')->label('Buffer')->suffix('%'),
                TextColumn::make('example')->label('RM 100 shows as')
                    ->state(fn (Currency $r) => $r->format($r->fromBase(100))),
                TextColumn::make('rate_source')->label('Source')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) { 'ecb' => 'Automatic (ECB)', 'er-api', 'currency-api' => 'Automatic (daily)', 'manual' => 'Manual', 'base' => 'Base', default => 'Not set yet' })
                    ->color(fn (?string $state) => match ($state) { 'ecb', 'er-api', 'currency-api' => 'success', 'manual' => 'warning', default => 'gray' })
                    ->placeholder('Not set yet'),
                TextColumn::make('rate_updated_at')->label('Rate last changed')->since()->placeholder('Never: please check'),
                ToggleColumn::make('is_active')->label('Active')
                    ->disabled(fn (Currency $r) => $r->is_base),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrencies::route('/'),
            'create' => CreateCurrency::route('/create'),
            'edit' => EditCurrency::route('/{record}/edit'),
        ];
    }
}
