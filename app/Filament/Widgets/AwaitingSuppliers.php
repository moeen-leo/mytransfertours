<?php

namespace App\Filament\Widgets;

use App\Enums\BookingItemStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\BookingItem;
use App\Support\SalesFigures as F;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Upcoming services the supplier hasn't confirmed yet, soonest first. */
class AwaitingSuppliers extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Needs attention: not confirmed by the supplier')
            ->query(F::awaitingSupplier()->with('booking'))
            ->defaultSort('service_date')
            ->columns([
                TextColumn::make('service_date')->label('Service')->date('D j M')->description(fn (BookingItem $r) => $r->service_time ? substr((string) $r->service_time, 0, 5) : null)->sortable(),
                TextColumn::make('reference')->label('Item')->searchable(),
                TextColumn::make('title')->limit(40)->tooltip(fn (BookingItem $r) => $r->title),
                TextColumn::make('supplier_name')->label('Supplier'),
                TextColumn::make('status')->badge()
                    ->color(fn (BookingItemStatus $state) => match ($state) {
                        BookingItemStatus::Rejected => 'danger',
                        BookingItemStatus::SentToSupplier => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('supplier_sent_at')->label('Emailed')->since()->placeholder('Not sent'),
            ])
            ->recordUrl(fn (BookingItem $r) => BookingResource::getUrl('view', ['record' => $r->booking_id]))
            ->emptyStateHeading('All upcoming services are confirmed')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }
}
