<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use App\Models\BookingChangeRequest;
use App\Services\BookingMailer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    /** Load what the page shows up front: dev runs Laravel's strict mode, where lazy loading throws. */
    protected function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        return parent::resolveRecord($key)->load(['items.transfer', 'items.tour', 'items.tickets', 'items.extras', 'items.booking']);
    }

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

            Action::make('voucher')
                ->label('Voucher')
                ->icon(Heroicon::OutlinedTicket)
                ->color('gray')
                ->url(fn (Booking $record) => route('vouchers.show', $record))
                ->openUrlInNewTab()
                ->visible(fn (Booking $record) => \App\Services\VoucherPdf::available($record)),

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
                        'settled_by' => \Filament\Facades\Filament::auth()->id(),
                    ])->save();

                    $record->log('settled', 'Marked as settled: '.$data['reference'], array_filter(['note' => $data['note'] ?? null]));

                    Notification::make()->title('Booking settled')->success()->send();
                }),

            Action::make('refund')
                ->label('Refund')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (Booking $record) => $record->payment_method === PaymentMethod::Card
                    && in_array($record->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true))
                ->modalHeading(fn (Booking $record) => 'Refund '.$record->reference)
                ->modalDescription(fn (Booking $record) => 'Money goes back to the customer\'s card through Stripe (usually 5–10 days). Paid: '
                    .$record->currency.' '.number_format((float) $record->total, 2)
                    .', already refunded: '.$record->currency.' '.number_format((float) $record->refunds()->where('status', 'succeeded')->sum('amount'), 2).'.')
                ->schema([
                    TextInput::make('amount')
                        ->label(fn (Booking $record) => 'Amount ('.$record->currency.')')
                        ->numeric()->minValue(0.01)->required()
                        ->default(fn (Booking $record) => round((float) $record->total - (float) $record->refunds()->where('status', 'succeeded')->sum('amount'), 2)),
                    TextInput::make('reason')->label('Reason')->placeholder('Customer cancelled within free cancellation')->required(),
                    Toggle::make('cancel')->label('Also cancel the booking')->default(true),
                ])
                ->requiresConfirmation()
                ->action(function (Booking $record, array $data) {
                    try {
                        app(\App\Services\StripeService::class)->refund($record, (float) $data['amount'], $data['reason'], \Filament\Facades\Filament::auth()->id());
                    } catch (\Throwable $e) {
                        Notification::make()->title('Refund failed')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    if ($data['cancel'] ?? false) {
                        $record->forceFill(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()])->save();
                        $record->log('cancelled', 'Booking cancelled with refund');
                    }

                    Notification::make()->title('Refund sent to Stripe')->success()->send();
                }),

            ActionGroup::make([
                Action::make('resendCustomer')
                    ->label('Resend confirmation to customer')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Booking $record) => 'Sends the booking confirmation again to '.$record->email.'.')
                    ->action(function (Booking $record) {
                        $log = app(BookingMailer::class)->customerConfirmation($record, 'manual', \Filament\Facades\Filament::auth()->id());
                        $this->notifyEmail($log);
                    }),
                Action::make('emailVoucher')
                    ->label('Email voucher to customer')
                    ->icon(Heroicon::OutlinedTicket)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Booking $record) => 'Sends the voucher PDF to '.$record->email.'.')
                    ->visible(fn (Booking $record) => \App\Services\VoucherPdf::available($record))
                    ->action(function (Booking $record) {
                        $log = app(BookingMailer::class)->customerVoucher($record, 'manual', \Filament\Facades\Filament::auth()->id());
                        $this->notifyEmail($log);
                    }),
                Action::make('resendSupplier')
                    ->label('Resend to supplier')
                    ->icon(Heroicon::OutlinedTruck)
                    ->schema([
                        Select::make('item_id')->label('Item')->required()
                            ->options(fn (Booking $record) => $record->items->mapWithKeys(fn ($i) => [$i->id => $i->reference.' · '.$i->title.' → '.$i->supplier_name])->all())
                            ->default(fn (Booking $record) => $record->items->first()?->id),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $item = $record->items->firstWhere('id', (int) $data['item_id']);
                        $log = app(BookingMailer::class)->supplierNewBooking($item, 'manual', \Filament\Facades\Filament::auth()->id());
                        $this->notifyEmail($log);
                    }),
                Action::make('customEmail')
                    ->label('Write an email…')
                    ->icon(Heroicon::OutlinedPencil)
                    ->schema([
                        Select::make('to')->label('To')->required()
                            ->options(fn (Booking $record) => ['customer' => 'Customer · '.$record->email]
                                + $record->items->mapWithKeys(fn ($i) => ['item:'.$i->id => 'Supplier · '.$i->supplier_name.' ('.$i->reference.')'])->all())
                            ->default('customer'),
                        TextInput::make('subject')->required()->default(fn (Booking $record) => 'Your booking '.$record->reference),
                        Textarea::make('body')->label('Message')->rows(8)->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $item = str_starts_with($data['to'], 'item:') ? $record->items->firstWhere('id', (int) substr($data['to'], 5)) : null;
                        $to = $item ? (string) $item->supplier_email : $record->email;
                        $log = app(BookingMailer::class)->custom($record, $item, $to, $data['subject'], $data['body'], \Filament\Facades\Filament::auth()->id());
                        $this->notifyEmail($log);
                    }),
            ])
                ->label('Email')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->button(),

            Action::make('changeRequest')
                ->label(fn (Booking $record) => 'Change request ('.$record->changeRequests->where('status', 'pending')->count().')')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning')
                ->visible(fn (Booking $record) => $record->changeRequests->where('status', 'pending')->isNotEmpty())
                ->modalHeading('Handle change request')
                ->fillForm(function (Booking $record) {
                    $req = $record->changeRequests->where('status', 'pending')->first();

                    return [
                        'request_id' => $req?->id,
                        'decision' => 'approved',
                        'new_date' => $req?->requested_date?->format('Y-m-d'),
                        'new_time' => $req?->requested_time ? substr((string) $req->requested_time, 0, 5) : null,
                    ];
                })
                ->schema([
                    Select::make('request_id')->label('Request')->required()
                        ->options(fn (Booking $record) => $record->changeRequests->where('status', 'pending')->mapWithKeys(fn ($r) => [
                            $r->id => ($r->item?->reference ?? 'Booking').' → '.trim(($r->requested_date?->format('D j M') ?? '').' '.($r->requested_time ? substr((string) $r->requested_time, 0, 5) : '')).($r->message ? ' · "'.\Illuminate\Support\Str::limit($r->message, 60).'"' : ''),
                        ])->all()),
                    Radio::make('decision')->options(['approved' => 'Approve and update the booking', 'declined' => 'Decline'])->required()->live(),
                    DatePicker::make('new_date')->label('New date')->native(false)
                        ->visible(fn ($get) => $get('decision') === 'approved'),
                    TimePicker::make('new_time')->label('New time')->seconds(false)
                        ->visible(fn ($get) => $get('decision') === 'approved'),
                    Textarea::make('note')->label('Message to the customer (optional)')->rows(3)
                        ->helperText('e.g. a price difference to settle, or why it can\'t be done.'),
                ])
                ->action(function (Booking $record, array $data) {
                    $req = BookingChangeRequest::query()->where('booking_id', $record->id)->findOrFail($data['request_id']);
                    $mailer = app(BookingMailer::class);

                    if ($data['decision'] === 'approved' && $req->item) {
                        $item = $req->item;
                        $before = $item->service_date->format('D j M Y').($item->service_time ? ' '.substr((string) $item->service_time, 0, 5) : '');
                        $item->forceFill(array_filter([
                            'service_date' => $data['new_date'] ?? null,
                            'service_time' => $data['new_time'] ?? null,
                        ]))->save();
                        $item->refresh();
                        $after = $item->service_date->format('D j M Y').($item->service_time ? ' '.substr((string) $item->service_time, 0, 5) : '');
                        if ($before !== $after) {
                            $mailer->supplierItemUpdated($item, ['Date & time' => ['from' => $before, 'to' => $after]]);
                        }
                    }

                    $req->forceFill([
                        'status' => $data['decision'],
                        'admin_note' => $data['note'] ?? null,
                        'handled_by' => \Filament\Facades\Filament::auth()->id(),
                        'handled_at' => now(),
                    ])->save();

                    $record->log('change_'.$data['decision'], 'Change request '.$data['decision'].($data['note'] ?? '' ? ': '.$data['note'] : ''));
                    $mailer->customerChangeDecision($req->fresh(['booking', 'item']));

                    Notification::make()->title('Change request '.$data['decision'])->success()->send();
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

    private function notifyEmail(\App\Models\EmailLog $log): void
    {
        $log->status === 'sent'
            ? Notification::make()->title('Email sent to '.$log->recipient)->success()->send()
            : Notification::make()->title('Email failed')->body($log->error)->danger()->persistent()->send();
    }
}
