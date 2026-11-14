<?php

namespace App\Filament\Resources\Customers;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\RelationManagers\BookingsRelationManager;
use App\Models\Currency;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Customers are users with the "customer" role. Staff and admins live under Team > Users. */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'customer';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Customer->value);
    }

    public static function currencyOptions(): array
    {
        return Currency::query()->orderBy('sort')->pluck('code', 'code')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->tel(),
                TextInput::make('country_code')->label('Country (2 letters)')->maxLength(2)->placeholder('GB'),
                Select::make('preferred_currency')
                    ->label('Account currency')
                    ->options(fn () => static::currencyOptions())
                    ->default('MYR')->required()
                    ->helperText('Prices and invoices for this customer are shown in this currency.'),
                TextInput::make('password')
                    ->password()->revealable()
                    ->rule(\Illuminate\Validation\Rules\Password::min(10))
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit'
                        ? 'Leave empty to keep their password.'
                        : 'Optional. Set one if you are opening the account for them (e.g. an agent).'),
            ]),

            Section::make('Billing details')
                ->description('Printed on their invoices.')
                ->columns(2)
                ->schema([
                    TextInput::make('company_name')->label('Company'),
                    TextInput::make('tax_number')->label('Tax / registration no.'),
                    Textarea::make('billing_address')->rows(3)->columnSpanFull(),
                ]),

            Section::make('Credit account')
                ->description('Lets this customer confirm bookings without paying by card, and settle with you later.')
                ->columns(3)
                ->schema([
                    Toggle::make('credit_enabled')
                        ->label('Allow "Pay on account"')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('credit_limit')
                        ->label('Credit limit')
                        ->numeric()->minValue(0)
                        ->prefix(fn (Get $get) => $get('preferred_currency') ?: 'MYR')
                        ->placeholder('No limit')
                        ->helperText('Unpaid on-account bookings can never exceed this.')
                        ->visible(fn (Get $get) => (bool) $get('credit_enabled')),
                    TextInput::make('credit_terms_days')
                        ->label('Payment terms')
                        ->numeric()->minValue(0)->maxValue(180)->default(30)
                        ->suffix('days')
                        ->helperText('Invoice due date = booking date + this.')
                        ->visible(fn (Get $get) => (bool) $get('credit_enabled')),
                    Text::make(function (?User $record) {
                        if (! $record) {
                            return '';
                        }
                        $cur = $record->preferred_currency ?: 'MYR';
                        $available = $record->creditAvailable();

                        return 'Outstanding now: '.$cur.' '.number_format($record->creditOutstanding(), 2)
                            .'  ·  Available: '.($available === null ? 'no limit' : $cur.' '.number_format($available, 2));
                    })
                        ->visible(fn (Get $get, ?User $record) => $record && (bool) $get('credit_enabled')),
                ]),

            Section::make('Internal notes')->collapsed()->schema([
                Textarea::make('admin_notes')->hiddenLabel()->rows(3)->helperText('Only visible to your team.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('bookings')
                ->withSum(['bookings as outstanding' => fn (Builder $q) => $q->outstandingCredit()], 'total'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('semibold')
                    ->description(fn (User $r) => $r->company_name),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('preferred_currency')->label('Currency')->badge()->color('gray'),
                TextColumn::make('credit_enabled')->label('Credit')
                    ->badge()
                    ->formatStateUsing(fn (bool $state, User $r) => $state
                        ? 'On account'.($r->credit_limit !== null ? ' · '.number_format((float) $r->credit_limit, 0) : '')
                        : 'Card only')
                    ->color(fn (bool $state) => $state ? 'primary' : 'gray'),
                TextColumn::make('outstanding')->label('Owes')
                    ->formatStateUsing(fn ($state, User $r) => (float) $state > 0 ? $r->preferred_currency.' '.number_format((float) $state, 2) : '—')
                    ->color(fn ($state) => (float) $state > 0 ? 'warning' : null),
                TextColumn::make('bookings_count')->label('Bookings')->alignCenter(),
                TextColumn::make('created_at')->label('Joined')->date()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('credit_enabled')->label('Credit account'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [BookingsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
