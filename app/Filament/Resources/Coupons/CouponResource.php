<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Promo codes';

    protected static ?string $modelLabel = 'promo code';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('WELCOME10')
                    ->alphaDash()
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper((string) $state)),
                Select::make('type')
                    ->options(['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount (RM)'])
                    ->default('percent')->required(),
                TextInput::make('value')->label('Discount')->numeric()->minValue(0)->required(),
                TextInput::make('min_order_amount')->label('Minimum order (RM)')->numeric()->minValue(0),
                TextInput::make('max_uses')->label('Total uses allowed')->numeric()->minValue(1)->placeholder('Unlimited'),
                TextInput::make('max_uses_per_user')->label('Uses per customer')->numeric()->minValue(1)->placeholder('Unlimited'),
                DateTimePicker::make('starts_at')->native(false)->seconds(false),
                DateTimePicker::make('ends_at')->native(false)->seconds(false)->afterOrEqual('starts_at'),
                TextInput::make('used_count')->label('Used so far')->disabled()->dehydrated(false)->visibleOn('edit'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->searchable()->weight('semibold')->copyable(),
                TextColumn::make('value')->label('Discount')
                    ->formatStateUsing(fn ($state, Coupon $r) => $r->type === 'percent' ? rtrim(rtrim((string) $state, '0'), '.').'%' : 'RM '.number_format((float) $state, 2)),
                TextColumn::make('used_count')->label('Used')
                    ->formatStateUsing(fn ($state, Coupon $r) => $state.($r->max_uses ? ' / '.$r->max_uses : '')),
                TextColumn::make('ends_at')->label('Ends')->date()->placeholder('No end date'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
