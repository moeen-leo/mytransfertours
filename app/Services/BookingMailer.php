<?php

namespace App\Services;

use App\Enums\BookingItemStatus;
use App\Enums\UserRole;
use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\BookingItem;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Every booking email goes through here, so each one is written to email_logs
 * (visible on the booking in the admin), a mail failure never breaks a booking,
 * and on dev every supplier email is redirected to MAIL_TEST_RECIPIENT.
 */
class BookingMailer
{
    // ---------------------------------------------------------------- flows

    /** A booking has just become Booked (paid by card or confirmed on account). */
    public function bookingPlaced(Booking $booking): void
    {
        $booking->loadMissing('items.transfer', 'items.extras');

        $this->customerConfirmation($booking);

        foreach ($booking->items as $item) {
            $this->supplierNewBooking($item);
        }
    }

    // ------------------------------------------------------------- customer

    public function customerConfirmation(Booking $booking, string $trigger = 'automatic', ?int $by = null): EmailLog
    {
        $booking->loadMissing('items.transfer', 'items.extras');

        return $this->deliver($booking, null, 'customer_confirmation', $booking->email, [],
            new BookingMail("Booking confirmed: {$booking->reference}", 'mail.customer.confirmation', [
                'booking' => $booking,
                'company' => $this->company(),
            ], $this->company()['email']),
            $trigger, $by);
    }

    /** Supplier confirmed and (maybe) added driver details. */
    public function customerItemConfirmed(BookingItem $item): EmailLog
    {
        $booking = $item->booking;

        return $this->deliver($booking, $item, 'customer_item_confirmed', $booking->email, [],
            new BookingMail("Confirmed: {$item->title} on {$item->service_date->format('j M')}", 'mail.customer.item-confirmed', [
                'booking' => $booking, 'item' => $item->loadMissing('transfer'), 'company' => $this->company(),
            ], $this->company()['email']));
    }

    public function customerChangeDecision(BookingChangeRequest $request): EmailLog
    {
        $booking = $request->booking;

        return $this->deliver($booking, $request->item, 'customer_change_'.$request->status, $booking->email, [],
            new BookingMail(
                ($request->status === 'approved' ? 'Change confirmed' : 'About your change request').": {$booking->reference}",
                'mail.customer.change-decision',
                ['booking' => $booking, 'request' => $request, 'company' => $this->company()],
                $this->company()['email'],
            ));
    }

    /** Free-text email from the admin to the customer or a supplier. */
    public function custom(Booking $booking, ?BookingItem $item, string $to, string $subject, string $body, ?int $by): EmailLog
    {
        return $this->deliver($booking, $item, 'manual', $to, [],
            new BookingMail($subject, 'mail.custom', ['body' => $body, 'booking' => $booking, 'company' => $this->company()], $this->company()['email']),
            'manual', $by, isSupplier: $item !== null && strcasecmp($to, (string) $item->supplier_email) === 0);
    }

    // ------------------------------------------------------------- supplier

    public function supplierNewBooking(BookingItem $item, string $trigger = 'automatic', ?int $by = null, bool $reminder = false): EmailLog
    {
        $item->loadMissing('booking', 'transfer', 'extras', 'supplier');
        $booking = $item->booking;

        $log = $this->deliver($booking, $item, $reminder ? 'supplier_reminder' : 'supplier_new_booking', (string) $item->supplier_email,
            (array) ($item->supplier?->cc_emails ?? []),
            new BookingMail(
                ($reminder ? 'Reminder: please confirm ' : 'New booking ').$item->reference.' · '.$item->product_type->getLabel().' · '.$item->service_date->format('D j M').($item->service_time ? ' '.substr((string) $item->service_time, 0, 5) : ''),
                'mail.supplier.new-booking',
                ['item' => $item, 'booking' => $booking, 'link' => self::supplierLink($item), 'reminder' => $reminder, 'company' => $this->company()],
                $this->company()['email'],
            ),
            $trigger, $by, isSupplier: true);

        if ($log->status === 'sent' && ! $reminder && $item->status === BookingItemStatus::Pending) {
            $item->forceFill(['status' => BookingItemStatus::SentToSupplier, 'supplier_sent_at' => now()])->save();
        }
        if ($reminder && $log->status === 'sent') {
            $item->forceFill(['reminder_count' => $item->reminder_count + 1, 'last_reminder_at' => now()])->save();
        }

        return $log;
    }

    /** The customer changed details (flight, hotel, notes…) after the supplier was told. */
    public function supplierItemUpdated(BookingItem $item, array $changes): EmailLog
    {
        $item->loadMissing('booking', 'transfer', 'supplier');

        return $this->deliver($item->booking, $item, 'supplier_update', (string) $item->supplier_email, (array) ($item->supplier?->cc_emails ?? []),
            new BookingMail("Updated booking {$item->reference} · {$item->service_date->format('D j M')}", 'mail.supplier.updated', [
                'item' => $item, 'booking' => $item->booking, 'changes' => $changes, 'link' => self::supplierLink($item), 'company' => $this->company(),
            ], $this->company()['email']),
            isSupplier: true);
    }

    // ------------------------------------------------------------------ ops

    /** Alerts your team (company email, else every admin). */
    public function opsAlert(Booking $booking, ?BookingItem $item, string $subject, string $message): void
    {
        $to = $this->company()['email'] ?: User::query()->where('role', UserRole::Admin)->value('email');
        if (! $to) {
            return;
        }

        $this->deliver($booking, $item, 'ops_alert', $to, [],
            new BookingMail("[MyTransferTours] {$subject}", 'mail.ops-alert', [
                'booking' => $booking, 'item' => $item, 'message' => $message,
                'adminUrl' => \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking], panel: 'admin'),
            ]));
    }

    // -------------------------------------------------------------- helpers

    /** Stable one-click link for the supplier: same link in every email for this item. */
    public static function supplierToken(BookingItem $item): string
    {
        return substr(hash_hmac('sha256', 'supplier|'.$item->id.'|'.$item->reference, (string) config('app.key')), 0, 40);
    }

    public static function supplierLink(BookingItem $item): string
    {
        return route('supplier.show', ['item' => $item->id, 'token' => self::supplierToken($item)]);
    }

    public function company(): array
    {
        return [
            'name' => Setting::get('company_name', 'MyTransferTours'),
            'email' => Setting::get('company_email') ?: config('mail.from.address'),
            'phone' => Setting::get('company_phone', ''),
        ];
    }

    private function deliver(
        Booking $booking,
        ?BookingItem $item,
        string $type,
        string $to,
        array $cc,
        BookingMail $mail,
        string $trigger = 'automatic',
        ?int $by = null,
        bool $isSupplier = false,
    ): EmailLog {
        $testRecipient = (string) config('booking.test_recipient');
        $actualTo = $to;
        $actualCc = array_values(array_filter($cc));

        // Dev safety: supplier emails go to the test recipient, with the real address in the subject.
        if ($isSupplier && $testRecipient !== '') {
            $mail->subjectLine = "[TEST · for {$to}] ".$mail->subjectLine;
            $actualTo = $testRecipient;
            $actualCc = [];
        }

        $log = EmailLog::query()->create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item?->id,
            'type' => $type,
            'trigger' => $trigger,
            'recipient' => $actualTo,
            'cc' => $actualCc ?: null,
            'subject' => $mail->subjectLine,
            'status' => 'queued',
            'sent_by_user_id' => $by,
        ]);

        if ($actualTo === '' || ! filter_var($actualTo, FILTER_VALIDATE_EMAIL)) {
            $log->forceFill(['status' => 'failed', 'error' => 'No valid email address'])->save();

            return $log;
        }

        try {
            $html = $mail->render();
            $pending = Mail::to($actualTo);
            if ($actualCc) {
                $pending->cc($actualCc);
            }
            $sent = $pending->send($mail);

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'body_html' => $html,
                'provider_message_id' => $sent?->getMessageId(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Booking email failed', ['type' => $type, 'to' => $actualTo, 'error' => $e->getMessage()]);
            $log->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $log;
    }
}
