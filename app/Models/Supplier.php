<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'cc_emails' => 'array',
        'bank_details' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function transferRoutes(): HasMany
    {
        return $this->hasMany(TransferRoute::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Counts for every record that points at this supplier (deleted tours and
     * tickets too: the database still links them). Used by the admin's
     * "Used in" column; a supplier can only be deleted when all are zero.
     */
    public static function usageCounts(): array
    {
        return [
            'transferRoutes as transfers_count',
            'tours as tours_count' => fn ($q) => $q->withTrashed(),
            'attractions as tickets_count' => fn ($q) => $q->withTrashed(),
            'bookingItems as bookings_count',
        ];
    }

    public function usageTotal(): int
    {
        if (! array_key_exists('transfers_count', $this->attributes)) {
            $this->loadCount(static::usageCounts());
        }

        return (int) $this->transfers_count + (int) $this->tours_count + (int) $this->tickets_count + (int) $this->bookings_count;
    }

    public function usageSummary(): string
    {
        $this->usageTotal();

        return collect([
            'transfer route' => $this->transfers_count,
            'tour' => $this->tours_count,
            'ticket' => $this->tickets_count,
            'booking' => $this->bookings_count,
        ])->filter()->map(fn ($n, $label) => $n.' '.\Illuminate\Support\Str::plural($label, (int) $n))->implode(' · ') ?: 'Not used anywhere';
    }
}
