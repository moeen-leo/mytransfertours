<?php

namespace App\Filament\Resources\Attractions;

use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Models\Attraction;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

class AttractionResource extends Resource
{
    protected static ?string $model = Attraction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Attraction tickets';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Attraction')->columns(2)->schema([
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
                Select::make('delivery_method')
                    ->label('How the customer gets the ticket')
                    ->options([
                        'instant_voucher' => 'Our voucher, sent instantly',
                        'supplier_eticket' => 'Supplier e-ticket, uploaded after confirmation',
                    ])
                    ->default('instant_voucher')->required(),
                Textarea::make('summary')->rows(2)->maxLength(500)->columnSpanFull(),
                MarkdownEditor::make('description')->columnSpanFull(),
                TagsInput::make('good_to_know')
                    ->label('Good to know')
                    ->placeholder('e.g. Arrive 15 minutes early')
                    ->columnSpanFull(),
            ]),

            Section::make('Ticket types & prices')
                ->description('Per person. Add one row per ticket type, e.g. Adult, Child, Malaysian (MyKad).')
                ->schema([
                    Repeater::make('ticketTypes')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('sort')
                        ->table([
                            TableColumn::make('Ticket type')->markAsRequired(),
                            TableColumn::make('Age / note'),
                            TableColumn::make('Cost (RM)')->markAsRequired(),
                            TableColumn::make('Selling (RM)')->markAsRequired(),
                            TableColumn::make('ID needed'),
                            TableColumn::make('On'),
                        ])
                        ->schema([
                            TextInput::make('name')->required()->placeholder('Adult'),
                            TextInput::make('age_label')->placeholder('13+'),
                            TextInput::make('cost_price')->numeric()->minValue(0)->required(),
                            TextInput::make('selling_price')->numeric()->minValue(0)->required()->gte('cost_price'),
                            Toggle::make('requires_id'),
                            Toggle::make('is_active')->default(true),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Add ticket type'),
                ]),

            Section::make('Opening & cancellation')->columns(2)->schema([
                CheckboxList::make('closed_weekdays')
                    ->label('Closed on')
                    ->options([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'])
                    ->columns(7)
                    ->helperText('These days are greyed out in the booking calendar. Add holidays and one-off closures later.')
                    ->columnSpanFull(),
                Toggle::make('is_refundable')->label('Refundable')->live(),
                TextInput::make('cancellation_hours')
                    ->label('Free cancellation')->numeric()->suffix('hours before')->default(24)
                    ->visible(fn (Get $get) => (bool) $get('is_refundable')),
            ]),

            Section::make('Photos')->schema([
                FileUpload::make('images')
                    ->hiddenLabel()
                    ->multiple()->reorderable()->maxFiles(10)
                    ->image()->imageEditor()
                    ->disk('uploads')->directory('attractions')->visibility('public')
                    ->maxSize(4096)
                    ->panelLayout('grid')
                    ->helperText('The first photo is the cover; drag to reorder.'),
            ]),

            Section::make()->columns(2)->schema([
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
            ->modifyQueryUsing(fn ($query) => $query->with(['city', 'supplier'])->withMin('ticketTypes', 'selling_price'))
            ->columns([
                ImageColumn::make('images')->disk('uploads')->label('')->imageHeight(44)->limit(1),
                TextColumn::make('title')->searchable()->weight('semibold')->wrap()
                    ->description(fn (Attraction $r) => $r->city?->name),
                TextColumn::make('ticket_types_min_selling_price')->label('From')->money('MYR'),
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
            'index' => ListAttractions::route('/'),
            'create' => CreateAttraction::route('/create'),
            'edit' => EditAttraction::route('/{record}/edit'),
        ];
    }
}
