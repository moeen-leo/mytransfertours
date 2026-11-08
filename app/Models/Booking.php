<?php

namespace App\Models;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['manage_token'];

    protected $casts = [
        'status' => BookingStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'payment_due_at' => 'date',
        'settled_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_total' => 'decimal:2',        // always in the base currency (MYR): what suppliers are paid
        'exchange_rate' => 'decimal:6',     // MYR -> booking currency rate used for the customer totals
        'stripe_charge_amount' => 'decimal:2',
        'stripe_fee' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= static::nextReference();
            $booking->manage_token ??= \Illuminate\Support\Str::random(48);
        });
    }

    /** MTT-10001, MTT-10002, ... Unique index on `reference` guards against races; retry on conflict. */
    public static function nextReference(): string
    {
        $last = static::withTrashed()->max('id') ?? 0;

        return 'MTT-'.(10001 + $last);
    }

    /**
     * Gives the booking its invoice number (INV-2026-00001), once. Called when a booking
     * becomes Booked, by card or on account. Numbers run per year without gaps.
     */
    public function assignInvoiceNumber(): void
    {
        if ($this->invoice_number) {
            return;
        }

        DB::transaction(function () {
            $year = now()->year;
            $prefix = "INV-{$year}-";
            $last = static::withTrashed()
                ->where('invoice_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->max('invoice_number');
            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            $this->forceFill([
                'invoice_number' => $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                'invoiced_at' => now(),
            ])->save();
        });
    }

    /** Copies the customer's current billing details onto the booking (for the invoice). */
    public function snapshotBilling(?User $user): void
    {
        if (! $user) {
            return;
        }

        $this->billing_company = $user->company_name;
        $this->billing_address = $user->billing_address;
        $this->billing_tax_number = $user->tax_number;
    }

    /** Unpaid bookings on a customer's credit account that still count against the limit. */
    public function scopeOutstandingCredit(Builder $query): Builder
    {
        return $query
            ->where('payment_method', PaymentMethod::Credit)
            ->where('payment_status', PaymentStatus::Unpaid)
            ->whereIn('status', [BookingStatus::Booked, BookingStatus::Confirmed, BookingStatus::Completed]);
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === PaymentStatus::Unpaid
            && $this->payment_due_at !== null
            && $this->payment_due_at->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('service_date')->orderBy('service_time');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BookingActivity::class)->latest('created_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(BookingChangeRequest::class)->latest();
    }

    /** The customer's private link to view and manage this booking (no login needed). */
    public function manageUrl(): string
    {
        return route('manage.show', ['token' => $this->manage_token]);
    }

    public function getLeadNameAttribute(): string
    {
        return trim($this->lead_first_name.' '.$this->lead_last_name);
    }

    /** Customer total converted back to MYR. */
    public function getTotalBaseAttribute(): float
    {
        $rate = (float) $this->exchange_rate ?: 1.0;

        return round((float) $this->total / $rate, 2);
    }

    /** In MYR: customer total minus supplier cost minus Stripe fee (Stripe settles in MYR). */
    public function getNetMarginAttribute(): float
    {
        return round($this->total_base - (float) $this->cost_total - (float) ($this->stripe_fee ?? 0), 2);
    }

    public function allItemsConfirmed(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (BookingItem $i) => in_array($i->status, [BookingItemStatus::Confirmed, BookingItemStatus::Completed, BookingItemStatus::Cancelled], true));
    }

    public function log(string $type, string $description, array $meta = [], ?int $itemId = null, ?int $userId = null): BookingActivity
    {
        return $this->activities()->create([
            'booking_item_id' => $itemId,
            'user_id' => $userId ?? auth('admin')->id() ?? auth('web')->id(),
            'type' => $type,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
