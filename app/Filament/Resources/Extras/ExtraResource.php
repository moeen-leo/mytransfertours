<?php

namespace App\Filament\Resources\Extras;

use App\Enums\ProductType;
use App\Filament\Resources\Extras\Pages\CreateExtra;
use App\Filament\Resources\Extras\Pages\EditExtra;
use App\Filament\Resources\Extras\Pages\ListExtras;
use App\Models\Extra;
use BackedEnum;
use Filament\Actions\EditAction;
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

class ExtraResource extends Resource
{
    protected static ?string $model = Extra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Add-ons';

    protected static ?string $modelLabel = 'add-on';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->description('Optional extras customers can add while booking, e.g. a child seat on a transfer.')
                ->columns(2)
                ->schema([
                    Select::make('product_type')
                        ->label('Offered on')
                        ->options([ProductType::Transfer->value => 'Transfers', ProductType::Tour->value => 'Tours'])
                        ->required(),
                    TextInput::make('name')->required()->placeholder('Child seat'),
                    TextInput::make('description')->columnSpanFull(),
                    TextInput::make('cost_price')->label('Cost (RM)')->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('selling_price')->label('Selling (RM)')->numeric()->minValue(0)->required()->gte('cost_price'),
                    TextInput::make('max_quantity')->label('Max per booking')->numeric()->minValue(1)->default(4),
                    TextInput::make('sort')->numeric()->default(0),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold'),
                TextColumn::make('product_type')->label('On')->badge(),
                TextColumn::make('cost_price')->label('Cost')->money('MYR'),
                TextColumn::make('selling_price')->label('Selling')->money('MYR'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtras::route('/'),
            'create' => CreateExtra::route('/create'),
            'edit' => EditExtra::route('/{record}/edit'),
        ];
    }
}
