<?php

namespace App\Models;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => BookingStatus::class,
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_total' => 'decimal:2',
        'stripe_charge_amount' => 'decimal:2',
        'stripe_fee' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= static::nextReference();
        });
    }

    /** MTT-10001, MTT-10002, ... Unique index on `reference` guards against races; retry on conflict. */
    public static function nextReference(): string
    {
        $last = static::withTrashed()->max('id') ?? 0;

        return 'MTT-'.(10001 + $last);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function getLeadNameAttribute(): string
    {
        return trim($this->lead_first_name.' '.$this->lead_last_name);
    }

    /** Selling total minus supplier cost minus Stripe fee. */
    public function getNetMarginAttribute(): float
    {
        return round((float) $this->total - (float) $this->cost_total - (float) ($this->stripe_fee ?? 0), 2);
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
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
