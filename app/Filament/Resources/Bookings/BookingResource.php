<?php

namespace App\Filament\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Booking;
use App\Models\BookingItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/** Bookings are created by the website checkout; the admin views and manages them here. */
class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $overdue = Booking::query()->outstandingCredit()->whereDate('payment_due_at', '<', today())->count();

        return $overdue ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Overdue on-account bookings';
    }

    public static function money(Booking $b, $amount): string
    {
        return $b->currency.' '.number_format((float) $amount, 2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Booking')
                ->columnSpan(2)
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('created_at')->label('Booked on')->dateTime('j M Y, H:i'),
                    TextEntry::make('lead_name')->label('Lead traveller'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('user.name')
                        ->label('Account')
                        ->placeholder('Guest checkout')
                        ->url(fn (Booking $r) => $r->user_id ? CustomerResource::getUrl('edit', ['record' => $r->user_id]) : null),
                    TextEntry::make('customer_notes')->label('Customer notes')->placeholder('—')->columnSpan(2),
                ]),

            Section::make('Payment')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('payment_method')->label('Paid by')->badge(),
                    TextEntry::make('payment_status')->label('Status')->badge()
                        ->helperText(fn (Booking $r) => $r->isOverdue() ? 'Overdue since '.$r->payment_due_at->format('j M Y') : null),
                    TextEntry::make('payment_due_at')->label('Due date')->date('j M Y')
                        ->visible(fn (Booking $r) => $r->payment_method === PaymentMethod::Credit),
                    TextEntry::make('paid_at')->label('Paid on')->dateTime('j M Y, H:i')->placeholder('—'),
                    TextEntry::make('settlement_reference')->label('Settlement ref.')->placeholder('—')
                        ->visible(fn (Booking $r) => $r->payment_method === PaymentMethod::Credit),
                    TextEntry::make('stripe_payment_intent_id')->label('Stripe payment')->placeholder('—')->copyable()
                        ->visible(fn (Booking $r) => $r->payment_method === PaymentMethod::Card)
                        ->url(fn (Booking $r) => $r->stripe_payment_intent_id ? 'https://dashboard.stripe.com/'.(str_starts_with((string) config('services.stripe.secret'), 'sk_test') ? 'test/' : '').'payments/'.$r->stripe_payment_intent_id : null, true),
                    TextEntry::make('refunded')->label('Refunded')
                        ->state(fn (Booking $r) => $r->refunds->where('status', 'succeeded')->map(fn ($x) => $r->currency.' '.number_format((float) $x->amount, 2).' · '.$x->created_at->format('j M').($x->reason ? ' · '.$x->reason : ''))->join("\n"))
                        ->visible(fn (Booking $r) => $r->refunds->isNotEmpty())
                        ->extraAttributes(['style' => 'white-space: pre-line']),
                ]),

            Section::make('Items')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('title')->weight('semibold')->columnSpan(2)
                                ->helperText(fn (BookingItem $i) => $i->reference.' · '.$i->product_type->getLabel()),
                            TextEntry::make('service_date')->label('Date')
                                ->formatStateUsing(fn (BookingItem $i) => $i->service_date->format('D j M Y').($i->service_time ? ' · '.substr((string) $i->service_time, 0, 5) : '')),
                            TextEntry::make('supplier_name')->label('Supplier')
                                ->helperText(fn (BookingItem $i) => $i->supplier_email),
                            TextEntry::make('status')->badge()
                                ->helperText(fn (BookingItem $i) => $i->transfer?->driver_name ? 'Driver: '.collect([$i->transfer->driver_name, $i->transfer->driver_phone, $i->transfer->vehicle_plate])->filter()->join(' · ') : ($i->supplier_notes ? 'Supplier: '.$i->supplier_notes : null)),
                            TextEntry::make('selling_total')->label('Price')
                                ->formatStateUsing(fn ($state, BookingItem $i) => static::money($i->booking, $state))
                                ->helperText(fn (BookingItem $i) => 'Cost MYR '.number_format((float) $i->cost_total, 2)),
                        ]),
                ]),

            Section::make('Totals')
                ->columnSpan(2)
                ->columns(4)
                ->schema([
                    TextEntry::make('subtotal')->formatStateUsing(fn ($state, Booking $r) => static::money($r, $state)),
                    TextEntry::make('discount_total')->label('Discount')
                        ->formatStateUsing(fn ($state, Booking $r) => (float) $state > 0 ? '− '.static::money($r, $state) : '—')
                        ->helperText(fn (Booking $r) => $r->coupon_code),
                    TextEntry::make('total')->weight('bold')->formatStateUsing(fn ($state, Booking $r) => static::money($r, $state)),
                    TextEntry::make('exchange_rate')->label('Rate used')
                        ->formatStateUsing(fn ($state, Booking $r) => $r->currency === 'MYR' ? '—' : '1 MYR = '.rtrim(rtrim((string) $state, '0'), '.').' '.$r->currency),
                    TextEntry::make('cost_total')->label('Supplier cost')->formatStateUsing(fn ($state) => 'MYR '.number_format((float) $state, 2)),
                    TextEntry::make('stripe_fee')->label('Stripe fee')->formatStateUsing(fn ($state) => $state !== null ? 'MYR '.number_format((float) $state, 2) : '—'),
                    TextEntry::make('net_margin')->label('Net margin')->weight('bold')->color('success')
                        ->state(fn (Booking $r) => 'MYR '.number_format($r->net_margin, 2)),
                ]),

            Section::make('Invoice')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('invoice_number')->placeholder('Not issued yet')->copyable(),
                    TextEntry::make('billing_company')->label('Bill to')
                        ->formatStateUsing(fn (Booking $r) => $r->billing_company ?: $r->lead_name)
                        ->helperText(fn (Booking $r) => trim(($r->billing_address ? $r->billing_address : '').($r->billing_tax_number ? "\nTax no. ".$r->billing_tax_number : '')) ?: null),
                ]),

            Section::make('Change requests')
                ->columnSpanFull()
                ->visible(fn (Booking $r) => $r->changeRequests->isNotEmpty())
                ->schema([
                    RepeatableEntry::make('changeRequests')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('created_at')->label('Asked')->since(),
                            TextEntry::make('item.reference')->label('Item')->placeholder('Whole booking'),
                            TextEntry::make('requested_date')->label('New date / time')
                                ->formatStateUsing(fn ($state, $record) => trim(($record->requested_date?->format('D j M Y') ?? '').' '.($record->requested_time ? substr((string) $record->requested_time, 0, 5) : '')) ?: '—'),
                            TextEntry::make('message')->placeholder('—'),
                            TextEntry::make('status')->badge()
                                ->color(fn (string $state) => ['pending' => 'warning', 'approved' => 'success', 'declined' => 'gray'][$state] ?? 'gray')
                                ->helperText(fn ($record) => $record->admin_note),
                        ]),
                ]),

            Section::make('Emails')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('emailLogs')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('created_at')->label('When')->dateTime('j M, H:i'),
                            TextEntry::make('type')->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                                ->helperText(fn ($record) => $record->trigger === 'manual' ? 'sent manually' : null),
                            TextEntry::make('recipient')->label('To'),
                            TextEntry::make('subject')->columnSpan(1),
                            TextEntry::make('status')->badge()
                                ->color(fn (string $state) => ['sent' => 'success', 'failed' => 'danger', 'queued' => 'gray'][$state] ?? 'gray')
                                ->helperText(fn ($record) => $record->error ? \Illuminate\Support\Str::limit($record->error, 120) : null),
                        ]),
                ]),

            Section::make('Internal notes')
                ->columnSpanFull()
                ->collapsed()
                ->schema([TextEntry::make('internal_notes')->hiddenLabel()->placeholder('No notes')]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('reference')->searchable()->weight('semibold')
                    ->description(fn (Booking $r) => $r->invoice_number),
                TextColumn::make('lead_last_name')->label('Customer')
                    ->formatStateUsing(fn (Booking $r) => $r->billing_company ?: $r->lead_name)
                    ->description(fn (Booking $r) => $r->email)
                    ->searchable(['lead_first_name', 'lead_last_name', 'email', 'billing_company']),
                TextColumn::make('items_count')->label('Items')->alignCenter(),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state, Booking $r) => static::money($r, $state))
                    ->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_method')->label('Paid by')->badge(),
                TextColumn::make('payment_status')->label('Payment')->badge()
                    ->description(fn (Booking $r) => $r->isOverdue() ? 'Overdue' : ($r->payment_status === PaymentStatus::Unpaid && $r->payment_due_at ? 'Due '.$r->payment_due_at->format('j M') : null)),
                TextColumn::make('created_at')->label('Booked')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
                SelectFilter::make('payment_method')->label('Paid by')->options(PaymentMethod::class),
                SelectFilter::make('payment_status')->label('Payment')->options(PaymentStatus::class),
                Filter::make('overdue')
                    ->label('Overdue on account')
                    ->query(fn (Builder $q) => $q->outstandingCredit()->whereDate('payment_due_at', '<', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Booking $r) => route('invoices.show', $r))
                    ->openUrlInNewTab()
                    ->visible(fn (Booking $r) => filled($r->invoice_number)),
                Action::make('voucher')
                    ->label('Voucher')
                    ->icon(Heroicon::OutlinedTicket)
                    ->url(fn (Booking $r) => route('vouchers.show', $r))
                    ->openUrlInNewTab()
                    ->visible(fn (Booking $r) => \App\Services\VoucherPdf::available($r)),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
