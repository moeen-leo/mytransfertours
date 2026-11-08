<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return 'Booking '.$this->record->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invoice')
                ->label('Invoice')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn (Booking $record) => route('invoices.show', $record))
                ->openUrlInNewTab()
                ->visible(fn (Booking $record) => filled($record->invoice_number)),

            Action::make('settle')
                ->label('Mark as settled')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (Booking $record) => $record->payment_method === PaymentMethod::Credit
                    && $record->payment_status === PaymentStatus::Unpaid)
                ->modalHeading(fn (Booking $record) => 'Settle '.$record->reference)
                ->modalDescription(fn (Booking $record) => 'Record that '.$record->currency.' '.number_format((float) $record->total, 2).' has been received. This frees up the customer\'s credit.')
                ->schema([
                    DatePicker::make('paid_on')->label('Received on')->default(today())->required()->native(false)->maxDate(today()),
                    TextInput::make('reference')->label('Payment reference')->placeholder('Bank transfer ref., cheque no. …')->required(),
                    Textarea::make('note')->label('Note (optional)')->rows(2),
                ])
                ->action(function (Booking $record, array $data) {
                    $record->forceFill([
                        'payment_status' => PaymentStatus::Paid,
                        'paid_at' => $data['paid_on'],
                        'settled_at' => now(),
                        'settlement_reference' => $data['reference'],
                        'settled_by' => auth()->id(),
                    ])->save();

                    $record->log('settled', 'Marked as settled: '.$data['reference'], array_filter(['note' => $data['note'] ?? null]));

                    Notification::make()->title('Booking settled')->success()->send();
                }),

            Action::make('note')
                ->label('Internal note')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->fillForm(fn (Booking $record) => ['internal_notes' => $record->internal_notes])
                ->schema([Textarea::make('internal_notes')->label('Visible to your team only')->rows(5)])
                ->action(function (Booking $record, array $data) {
                    $record->forceFill(['internal_notes' => $data['internal_notes']])->save();
                    Notification::make()->title('Note saved')->success()->send();
                }),
        ];
    }
}
