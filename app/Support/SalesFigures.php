<?php

namespace App\Support;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the admin dashboard. Everything is in the base currency (MYR), converted
 * with each booking's own exchange rate, and "today / this month" follow Malaysian time.
 */
class SalesFigures
{
    public const TIMEZONE = 'Asia/Kuala_Lumpur';

    /** A sale = paid by card or confirmed on account, and not cancelled. */
    public const SOLD = [BookingStatus::Booked, BookingStatus::Confirmed, BookingStatus::Completed];

    /** SQL for a booking's customer total in MYR. */
    private const TOTAL_BASE = 'bookings.total / COALESCE(NULLIF(bookings.exchange_rate, 0), 1)';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /** [start, end) in UTC for a period: today, month, last_month. */
    public static function range(string $period): array
    {
        $now = self::now();

        [$from, $to] = match ($period) {
            'today' => [$now->startOfDay(), $now->startOfDay()->addDay()],
            'last_month' => [$now->startOfMonth()->subMonth(), $now->startOfMonth()],
            default => [$now->startOfMonth(), $now->startOfMonth()->addMonth()],
        };

        return [$from->utc(), $to->utc()];
    }

    public static function sold(): Builder
    {
        return Booking::query()->whereIn('bookings.status', array_map(fn ($s) => $s->value, self::SOLD));
    }

    public static function soldIn(string $period): Builder
    {
        [$from, $to] = self::range($period);

        return self::sold()->where('bookings.created_at', '>=', $from)->where('bookings.created_at', '<', $to);
    }

    public static function bookings(string $period): int
    {
        return self::soldIn($period)->count();
    }

    /** Customer totals in MYR (before refunds). */
    public static function sales(string $period): float
    {
        return round((float) self::soldIn($period)->sum(DB::raw(self::TOTAL_BASE)), 2);
    }

    /** Refunds issued in the period, in MYR. */
    public static function refunds(string $period): float
    {
        [$from, $to] = self::range($period);

        return round((float) DB::table('refunds')
            ->join('bookings', 'bookings.id', '=', 'refunds.booking_id')
            ->where('refunds.status', 'succeeded')
            ->where('refunds.created_at', '>=', $from)->where('refunds.created_at', '<', $to)
            ->sum(DB::raw('refunds.amount / COALESCE(NULLIF(bookings.exchange_rate, 0), 1)')), 2);
    }

    /** Sales minus supplier cost, Stripe fees and refunds on those same bookings, in MYR. */
    public static function profit(string $period): float
    {
        $soldIds = self::soldIn($period)->select('bookings.id');

        $margin = (float) self::soldIn($period)
            ->sum(DB::raw(self::TOTAL_BASE.' - bookings.cost_total - COALESCE(bookings.stripe_fee, 0)'));

        $refunded = (float) DB::table('refunds')
            ->join('bookings', 'bookings.id', '=', 'refunds.booking_id')
            ->where('refunds.status', 'succeeded')
            ->whereIn('refunds.booking_id', $soldIds)
            ->sum(DB::raw('refunds.amount / COALESCE(NULLIF(bookings.exchange_rate, 0), 1)'));

        return round($margin - $refunded, 2);
    }

    /** Daily sales in MYR for the last $days days (oldest first), keyed by Y-m-d. */
    public static function dailySales(int $days): array
    {
        $start = self::now()->startOfDay()->subDays($days - 1);
        $series = [];
        for ($d = $start; $d->lessThanOrEqualTo(self::now()); $d = $d->addDay()) {
            $series[$d->format('Y-m-d')] = 0.0;
        }

        self::sold()->where('bookings.created_at', '>=', $start->utc())
            ->get(['bookings.created_at', 'bookings.total', 'bookings.exchange_rate'])
            ->each(function (Booking $b) use (&$series) {
                $key = $b->created_at->copy()->timezone(self::TIMEZONE)->format('Y-m-d');
                if (isset($series[$key])) {
                    $series[$key] += $b->total_base;
                }
            });

        return array_map(fn ($v) => round($v, 2), $series);
    }

    /** Monthly sales in MYR for the last $months months (oldest first), keyed by Y-m. */
    public static function monthlySales(int $months): array
    {
        $start = self::now()->startOfMonth()->subMonths($months - 1);
        $series = [];
        for ($m = $start; $m->lessThanOrEqualTo(self::now()); $m = $m->addMonth()) {
            $series[$m->format('Y-m')] = 0.0;
        }

        self::sold()->where('bookings.created_at', '>=', $start->utc())
            ->get(['bookings.created_at', 'bookings.total', 'bookings.exchange_rate'])
            ->each(function (Booking $b) use (&$series) {
                $key = $b->created_at->copy()->timezone(self::TIMEZONE)->format('Y-m');
                if (isset($series[$key])) {
                    $series[$key] += $b->total_base;
                }
            });

        return array_map(fn ($v) => round($v, 2), $series);
    }

    /** Items whose supplier hasn't confirmed yet (or declined), for upcoming services. */
    public static function awaitingSupplier(): Builder
    {
        return BookingItem::query()
            ->whereIn('booking_items.status', [BookingItemStatus::Pending->value, BookingItemStatus::SentToSupplier->value, BookingItemStatus::Rejected->value])
            ->whereDate('booking_items.service_date', '>=', self::now()->toDateString())
            ->whereHas('booking', fn (Builder $q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::SOLD)));
    }

    /** On-account bookings not yet paid: [amount in MYR, count, overdue count]. */
    public static function unpaidOnAccount(): array
    {
        $q = self::sold()
            ->where('bookings.payment_method', PaymentMethod::Credit->value)
            ->where('bookings.payment_status', PaymentStatus::Unpaid->value);

        return [
            round((float) (clone $q)->sum(DB::raw(self::TOTAL_BASE)), 2),
            (clone $q)->count(),
            (clone $q)->whereDate('bookings.payment_due_at', '<', self::now()->toDateString())->count(),
        ];
    }

    /** Best sellers this period: [{type, title, qty, sales}] by MYR sales. */
    public static function topProducts(string $period, int $limit = 6): array
    {
        [$from, $to] = self::range($period);

        return DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereIn('bookings.status', array_map(fn ($s) => $s->value, self::SOLD))
            ->where('bookings.created_at', '>=', $from)->where('bookings.created_at', '<', $to)
            ->where('booking_items.status', '!=', BookingItemStatus::Cancelled->value)
            ->groupBy('booking_items.product_type', 'booking_items.title')
            ->selectRaw('booking_items.product_type as type, booking_items.title as title, COUNT(*) as qty, SUM(booking_items.selling_total / COALESCE(NULLIF(bookings.exchange_rate, 0), 1)) as sales')
            ->orderByDesc('sales')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['type' => $r->type, 'title' => $r->title, 'qty' => (int) $r->qty, 'sales' => round((float) $r->sales, 2)])
            ->all();
    }

    public static function money(float $amount): string
    {
        return Currency::base().' '.number_format($amount, 2);
    }

    /** "+12% vs last month" style comparison, or null when there's nothing to compare. */
    public static function change(float $now, float $before): ?string
    {
        if ($before <= 0) {
            return null;
        }
        $pct = round(($now - $before) / $before * 100);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }
}
