<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Company name')->required()->columnSpanFull(),
                    TextInput::make('contact_name')->label('Contact person'),
                    TextInput::make('email')
                        ->label('Booking email')
                        ->email()->required()
                        ->helperText('New bookings are emailed here automatically.'),
                    TagsInput::make('cc_emails')
                        ->label('Also email (CC)')
                        ->placeholder('Add an email and press Enter')
                        ->nestedRecursiveRules(['email'])
                        ->columnSpanFull(),
                    TextInput::make('phone')->tel(),
                    TextInput::make('whatsapp')->label('WhatsApp')->tel(),
                ]),
            Section::make('Payment')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('payment_terms')->placeholder('Monthly, 30 days'),
                    Textarea::make('bank_details')
                        ->rows(3)
                        ->helperText('Stored encrypted.')
                        ->columnSpanFull(),
                ]),
            Section::make()
                ->schema([
                    Textarea::make('notes')->label('Internal notes')->rows(3),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(Supplier::usageCounts()))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Supplier $r) => $r->contact_name),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('whatsapp')->label('WhatsApp')->placeholder('—'),
                TextColumn::make('used_in')->label('Used in')
                    ->state(fn (Supplier $r) => $r->usageTotal())
                    ->formatStateUsing(fn (int $state) => $state === 0 ? 'Not used' : $state.' '.\Illuminate\Support\Str::plural('record', $state))
                    ->badge()->color(fn (int $state) => $state === 0 ? 'gray' : 'success')
                    ->tooltip(fn (Supplier $r) => $r->usageSummary()),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                static::deleteAction(DeleteAction::make()),
                static::deleteAction(ForceDeleteAction::make()),
                RestoreAction::make(),
            ]);
    }

    /**
     * Delete only a supplier nothing points at. A used one shows a disabled button
     * with the reason, so the team knows to move its products to another supplier first.
     */
    public static function deleteAction(DeleteAction|ForceDeleteAction $action): DeleteAction|ForceDeleteAction
    {
        return $action
            ->disabled(fn (Supplier $record) => $record->usageTotal() > 0)
            ->tooltip(fn (Supplier $record) => $record->usageTotal() > 0
                ? 'Used in '.$record->usageSummary().'. Move those to another supplier first.'
                : null);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
