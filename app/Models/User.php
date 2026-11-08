<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable;

    /**
     * `role` and the credit settings are deliberately not fillable, so a customer can never
     * promote themselves or grant themselves credit. Only admin screens set them.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'phone',
        'country_code',
        'marketing_opt_in',
        'company_name',
        'billing_address',
        'tax_number',
        'preferred_currency',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'marketing_opt_in' => 'boolean',
            'credit_enabled' => 'boolean',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Total of this customer's unpaid on-account bookings. */
    public function creditOutstanding(): float
    {
        return round((float) $this->bookings()->outstandingCredit()->sum('total'), 2);
    }

    /** How much more they can book on account. Null limit = no limit. */
    public function creditAvailable(): ?float
    {
        if (! $this->credit_enabled) {
            return 0.0;
        }

        return $this->credit_limit === null ? null : max(0, round((float) $this->credit_limit - $this->creditOutstanding(), 2));
    }

    public function canBookOnCredit(float $amount): bool
    {
        if (! $this->credit_enabled) {
            return false;
        }

        $available = $this->creditAvailable();

        return $available === null || $amount <= $available;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Staff], true);
    }

    /** Only admin and staff accounts can open the admin panel; customers are refused. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }
}
