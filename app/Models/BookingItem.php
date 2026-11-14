<?php

namespace App\Models;

use App\Enums\BookingItemStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class BookingItem extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['supplier_token_hash'];

    protected $casts = [
        'product_type' => ProductType::class,
        'status' => BookingItemStatus::class,
        'service_date' => 'date',
        'cost_total' => 'decimal:2',
        'selling_total' => 'decimal:2',
        'supplier_sent_at' => 'datetime',
        'supplier_confirmed_at' => 'datetime',
        'supplier_rejected_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'cancellation_deadline' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(BookingItemTransfer::class);
    }

    public function tour(): HasOne
    {
        return $this->hasOne(BookingItemTour::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(BookingItemTicket::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(BookingItemExtra::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class)->latest();
    }

    public function getMarginAttribute(): float
    {
        return round((float) $this->selling_total - (float) $this->cost_total, 2);
    }

    /**
     * Creates a fresh one-click token for the supplier email. Only the sha256 hash is
     * stored; the plain token goes into the signed confirm / reject URLs.
     */
    public function issueSupplierToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['supplier_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public static function findBySupplierToken(string $token): ?self
    {
        return static::where('supplier_token_hash', hash('sha256', $token))->first();
    }
}
