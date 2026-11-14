<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->weight('semibold')
                    ->description(fn (Booking $r) => $r->invoice_number),
                TextColumn::make('created_at')->label('Booked')->date(),
                TextColumn::make('total')->money(fn (Booking $r) => $r->currency),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_method')->label('Paid by')->badge(),
                TextColumn::make('payment_status')->label('Payment')->badge()
                    ->description(fn (Booking $r) => $r->isOverdue() ? 'Overdue' : ($r->payment_due_at ? 'Due '.$r->payment_due_at->format('j M') : null)),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Booking $r) => BookingResource::getUrl('view', ['record' => $r])),
            ]);
    }
}
