<?php

namespace App\Http\Controllers;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use App\Models\BookingItem;
use App\Services\BookingMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page behind "Confirm or decline" in supplier emails. No login: the link
 * carries a token tied to this booking item. Suppliers only ever see their net rate.
 */
class SupplierBookingController extends Controller
{
    public function __construct(private BookingMailer $mailer) {}

    public function show(BookingItem $item, string $token): View
    {
        $this->authorizeLink($item, $token);
        $item->load('booking', 'transfer', 'tour', 'tickets', 'extras');

        return view('supplier.booking', ['item' => $item, 'token' => $token, 'locked' => $this->locked($item)]);
    }

    public function confirm(Request $request, BookingItem $item, string $token): RedirectResponse
    {
        $this->authorizeLink($item, $token);
        abort_if($this->locked($item), 403, 'This booking can no longer be changed.');

        $data = $request->validate([
            'driver_name' => ['nullable', 'string', 'max:80'],
            'driver_phone' => ['nullable', 'string', 'max:32'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'supplier_reference' => ['nullable', 'string', 'max:60'],
            'supplier_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $wasConfirmed = $item->status === BookingItemStatus::Confirmed;
        $hadDriver = (bool) $item->transfer?->driver_name;

        $item->forceFill([
            'status' => BookingItemStatus::Confirmed,
            'supplier_confirmed_at' => $item->supplier_confirmed_at ?? now(),
            'supplier_rejected_at' => null,
            'supplier_reference' => $data['supplier_reference'] ?? $item->supplier_reference,
            'supplier_notes' => $data['supplier_notes'] ?? $item->supplier_notes,
        ])->save();

        if ($item->transfer) {
            $item->transfer->forceFill(array_filter([
                'driver_name' => $data['driver_name'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
            ], fn ($v) => filled($v)))->save();
        }

        $booking = $item->booking;
        $driverNow = (bool) $item->transfer?->fresh()?->driver_name;
        $booking->log($wasConfirmed ? 'supplier_updated' : 'supplier_confirmed',
            ($wasConfirmed ? 'Supplier updated ' : 'Supplier confirmed ').$item->reference.($driverNow ? ' (driver details added)' : ''),
            array_filter($data), $item->id, null);

        $nowFullyConfirmed = false;
        if ($booking->status === BookingStatus::Booked && $booking->fresh('items')->allItemsConfirmed()) {
            $booking->forceFill(['status' => BookingStatus::Confirmed])->save();
            $nowFullyConfirmed = true;
        }

        if ($nowFullyConfirmed) {
            // Last partner confirmed: one "booking confirmed" email with the voucher attached.
            $this->mailer->customerVoucher($booking->fresh());
        } elseif (! $wasConfirmed || (! $hadDriver && $driverNow)) {
            // Otherwise tell the customer when an item is first confirmed, and when driver details first appear.
            $this->mailer->customerItemConfirmed($item->fresh(['booking', 'transfer', 'tour', 'tickets', 'extras']));
        }

        return redirect()->route('supplier.show', ['item' => $item->id, 'token' => $token])
            ->with('status', $wasConfirmed ? 'Details updated. Thank you!' : 'Booking confirmed. Thank you!');
    }

    public function decline(Request $request, BookingItem $item, string $token): RedirectResponse
    {
        $this->authorizeLink($item, $token);
        abort_if($this->locked($item), 403, 'This booking can no longer be changed.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Please tell us why, so we can find an alternative quickly.']);

        $item->forceFill([
            'status' => BookingItemStatus::Rejected,
            'supplier_rejected_at' => now(),
            'supplier_notes' => $data['reason'],
        ])->save();

        $item->booking->log('supplier_declined', 'Supplier declined '.$item->reference.': '.$data['reason'], [], $item->id, null);
        $this->mailer->opsAlert($item->booking, $item, 'Supplier declined '.$item->reference,
            "{$item->supplier_name} can't do this booking. Reason: {$data['reason']}\n\nPlease arrange an alternative supplier and update the customer.");

        return redirect()->route('supplier.show', ['item' => $item->id, 'token' => $token])
            ->with('status', 'Thanks for letting us know. We\'ll arrange an alternative.');
    }

    private function authorizeLink(BookingItem $item, string $token): void
    {
        abort_unless(hash_equals(BookingMailer::supplierToken($item), $token), 404);
    }

    /** No changes once the booking is cancelled or the service date is more than a day in the past. */
    private function locked(BookingItem $item): bool
    {
        return in_array($item->status, [BookingItemStatus::Cancelled, BookingItemStatus::Completed], true)
            || $item->booking->status === BookingStatus::Cancelled
            || $item->service_date->lt(today()->subDay());
    }
}
