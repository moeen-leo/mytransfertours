<?php

namespace App\Console\Commands;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use App\Models\BookingItem;
use App\Services\BookingMailer;
use Illuminate\Console\Command;

/**
 * Chases suppliers who haven't confirmed: a reminder every SUPPLIER_REMINDER_HOURS
 * (default 4), at most 2, then your team gets an alert. Services starting within
 * 24 hours are chased at every run and alerted immediately.
 */
class SupplierReminders extends Command
{
    protected $signature = 'app:supplier-reminders';

    protected $description = 'Remind suppliers to confirm bookings and alert the team when they do not';

    private const MAX_REMINDERS = 2;

    public function handle(BookingMailer $mailer): int
    {
        $hours = max(1, (int) config('booking.supplier_reminder_hours', 4));

        $items = BookingItem::query()
            ->with(['booking', 'transfer', 'extras', 'supplier'])
            ->where('status', BookingItemStatus::SentToSupplier)
            ->whereDate('service_date', '>=', today())
            ->whereHas('booking', fn ($q) => $q->whereIn('status', [BookingStatus::Booked, BookingStatus::Confirmed]))
            ->get();

        foreach ($items as $item) {
            $soon = $item->service_date->lte(today()->addDay());
            $last = $item->last_reminder_at ?? $item->supplier_sent_at;
            $due = $last === null || $last->lte(now()->subHours($soon ? 1 : $hours));

            if (! $due) {
                continue;
            }

            if ($item->reminder_count < self::MAX_REMINDERS) {
                $firstReminder = $item->reminder_count === 0;
                $mailer->supplierNewBooking($item, 'automatic', null, reminder: true);
                $this->line("Reminded {$item->supplier_name} about {$item->reference}");

                if ($soon && $firstReminder) {
                    $mailer->opsAlert($item->booking, $item, "Unconfirmed, service within 24h: {$item->reference}",
                        "{$item->supplier_name} hasn't confirmed and the service is within 24 hours. Please call them.");
                }

                continue;
            }

            // Out of reminders: alert the team once.
            if (! $item->booking->activities()->where('type', 'ops_alert_unconfirmed')->where('booking_item_id', $item->id)->exists()) {
                $mailer->opsAlert($item->booking, $item, "Supplier not responding: {$item->reference}",
                    "{$item->supplier_name} hasn't confirmed after ".self::MAX_REMINDERS.' reminders. Please contact them or arrange another supplier.');
                $item->booking->log('ops_alert_unconfirmed', 'Team alerted: supplier not responding for '.$item->reference, [], $item->id, null);
            }
        }

        return self::SUCCESS;
    }
}
