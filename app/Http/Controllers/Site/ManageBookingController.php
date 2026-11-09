<?php

namespace App\Http\Controllers\Site;

use App\Enums\BookingItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Setting;
use App\Services\BookingMailer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Manage my booking" for every customer, signed in or not. Access is by the
 * private link in the confirmation email, or reference + email on "Find my booking".
 */
class ManageBookingController extends Controller
{
    public function __construct(private BookingMailer $mailer) {}

    // ---------------------------------------------------------------- find

    public function findForm(): View
    {
        return view('site.manage.find');
    }

    public function find(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:24'],
            'email' => ['required', 'email'],
        ]);

        $key = 'find-booking:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['reference' => 'Too many attempts. Please try again in a few minutes.']);
        }
        RateLimiter::hit($key, 600);

        $booking = Booking::query()
            ->where('reference', strtoupper(trim($data['reference'])))
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages(['reference' => 'We couldn\'t find a booking with that reference and email. Check both, or contact us.']);
        }

        return redirect()->to($booking->manageUrl());
    }

    // -------------------------------------------------------------- manage

    public function show(string $token): View
    {
        $booking = $this->booking($token);

        return view('site.manage.show', [
            'booking' => $booking,
            'editable' => $booking->items->mapWithKeys(fn (BookingItem $i) => [$i->id => $this->canEdit($booking, $i)])->all(),
            'cutoffHours' => (int) config('booking.customer_edit_cutoff_hours', 24),
        ]);
    }

    public function update(Request $request, string $token, BookingItem $item): RedirectResponse
    {
        $booking = $this->booking($token);
        abort_unless($item->booking_id === $booking->id, 404);

        if (! $this->canEdit($booking, $item)) {
            return back()->withErrors(['edit' => 'This booking can no longer be changed online. Please contact us.']);
        }

        $t = $item->transfer;
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'flight_number' => [$t?->pickupLocation?->requires_flight_number ? 'required' : 'nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]{2}\s?\d{1,4}[A-Za-z]?$/'],
            'pickup_address' => [$t?->pickupLocation?->requires_address ? 'required' : 'nullable', 'string', 'max:255'],
            'dropoff_address' => [$t?->dropoffLocation?->requires_address ? 'required' : 'nullable', 'string', 'max:255'],
            'sign_name' => ['nullable', 'string', 'max:60'],
            'driver_notes' => ['nullable', 'string', 'max:500'],
        ], ['flight_number.regex' => 'Please enter a flight number like MH 123.']);

        $changes = [];
        $track = function (string $label, $from, $to) use (&$changes) {
            if (trim((string) $from) !== trim((string) $to)) {
                $changes[$label] = ['from' => (string) $from, 'to' => (string) $to];
            }
        };

        $track('Phone', $booking->phone, $data['phone']);
        $booking->forceFill(['phone' => $data['phone']])->save();

        if ($t) {
            $new = [
                'flight_number' => strtoupper(trim((string) ($data['flight_number'] ?? ''))) ?: null,
                'pickup_address' => trim((string) ($data['pickup_address'] ?? '')) ?: null,
                'dropoff_address' => trim((string) ($data['dropoff_address'] ?? '')) ?: null,
                'sign_name' => trim((string) ($data['sign_name'] ?? '')) ?: $t->sign_name,
                'driver_notes' => trim((string) ($data['driver_notes'] ?? '')) ?: null,
            ];
            $track('Flight', $t->flight_number, $new['flight_number']);
            $track('Pick-up address', $t->pickup_address, $new['pickup_address']);
            $track('Drop-off address', $t->dropoff_address, $new['dropoff_address']);
            $track('Name on sign', $t->sign_name, $new['sign_name']);
            $track('Notes', $t->driver_notes, $new['driver_notes']);
            $t->forceFill($new)->save();
        }

        if ($changes === []) {
            return back()->with('status', 'Nothing changed.');
        }

        $booking->log('customer_updated', 'Customer updated '.$item->reference.': '.implode(', ', array_keys($changes)), $changes, $item->id, null);

        if (in_array($item->status, [BookingItemStatus::SentToSupplier, BookingItemStatus::Confirmed], true)) {
            $this->mailer->supplierItemUpdated($item->fresh(['booking', 'transfer', 'supplier']), $changes);
        }

        return back()->with('status', 'Saved. We\'ve let the driver\'s company know.');
    }

    public function requestChange(Request $request, string $token): RedirectResponse
    {
        $booking = $this->booking($token);

        $data = $request->validate([
            'booking_item_id' => ['required', 'integer'],
            'requested_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'requested_time' => ['nullable', 'date_format:H:i'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $booking->items->firstWhere('id', (int) $data['booking_item_id']);
        abort_unless($item, 404);

        if ($booking->changeRequests()->where('status', 'pending')->where('booking_item_id', $item->id)->exists()) {
            return back()->withErrors(['change' => 'You already have a change request waiting for this item. We\'ll reply soon.']);
        }

        $req = $booking->changeRequests()->create([
            'booking_item_id' => $item->id,
            'requested_date' => $data['requested_date'],
            'requested_time' => $data['requested_time'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        $booking->log('change_requested', 'Customer asked to move '.$item->reference.' to '.$data['requested_date'].(isset($data['requested_time']) ? ' '.$data['requested_time'] : ''), [], $item->id, null);
        $this->mailer->opsAlert($booking, $item, 'Change request for '.$booking->reference,
            'The customer asked to move '.$item->reference.' to '.CarbonImmutable::parse($data['requested_date'])->format('D j M Y').(isset($data['requested_time']) ? ' at '.$data['requested_time'] : '').'.'
            .($req->message ? "\n\nTheir message: ".$req->message : '')."\n\nApprove or decline it on the booking in the admin.");

        return back()->with('status', 'Request sent. We\'ll check with our partner and email you, usually within a few hours.');
    }

    public function invoice(string $token): View
    {
        $booking = $this->booking($token);
        abort_unless(filled($booking->invoice_number), 404);
        $booking->load(['items.transfer', 'items.tour', 'items.tickets', 'items.extras']);

        return view('invoices.show', [
            'booking' => $booking,
            'company' => [
                'name' => Setting::get('company_name', 'MyTransferTours'),
                'address' => Setting::get('company_address', ''),
                'registration' => Setting::get('company_registration', ''),
                'email' => Setting::get('company_email', config('mail.from.address')),
                'phone' => Setting::get('company_phone', ''),
                'bank' => Setting::get('company_bank_details', ''),
            ],
        ]);
    }

    // ------------------------------------------------------------- helpers

    private function booking(string $token): Booking
    {
        abort_unless(strlen($token) >= 32, 404);

        return Booking::query()
            ->where('manage_token', $token)
            ->with(['items.transfer.pickupLocation', 'items.transfer.dropoffLocation', 'items.tour', 'items.tickets', 'items.extras', 'changeRequests'])
            ->firstOrFail();
    }

    private function canEdit(Booking $booking, BookingItem $item): bool
    {
        if (! $booking->status->isActive() || in_array($item->status, [BookingItemStatus::Cancelled, BookingItemStatus::Completed, BookingItemStatus::Rejected], true)) {
            return false;
        }

        $start = CarbonImmutable::parse($item->service_date->format('Y-m-d').' '.($item->service_time ? substr((string) $item->service_time, 0, 5) : '00:00'), config('app.timezone'));

        return now()->lt($start->subHours((int) config('booking.customer_edit_cutoff_hours', 24)));
    }
}
