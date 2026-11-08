# MyTransferTours

Transfers, tours and attraction tickets in one cart, paid once with Stripe Checkout. Malaysia first, built to go global.

This commit is **step 1: the database layer**: migrations, Eloquent models and enums. The design is documented in [`docs/DATABASE.md`](docs/DATABASE.md).

## Add to a Laravel project

These files drop into a fresh Laravel 11 or 12 app:

```bash
composer create-project laravel/laravel mytransfertours
# copy app/, database/ and docs/ from this repo over the new project
php artisan migrate
```

### Two small edits to existing Laravel files

**1. `app/Providers/AppServiceProvider.php`.** Short, stable names in the polymorphic columns (so renaming a class never breaks old bookings):

```php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::enforceMorphMap([
        'transfer_route' => \App\Models\TransferRoute::class,
        'tour'           => \App\Models\Tour::class,
        'attraction'     => \App\Models\Attraction::class,
    ]);
}
```

**2. `app/Models/User.php`.** Add the new fields and relations:

```php
use App\Enums\UserRole;

protected $fillable = ['name', 'email', 'password', 'google_id', 'avatar', 'phone', 'country_code', 'marketing_opt_in'];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'marketing_opt_in' => 'boolean',
    ];
}

public function bookings() { return $this->hasMany(\App\Models\Booking::class); }
public function isAdmin(): bool { return $this->role === UserRole::Admin; }
```

`role` is deliberately left out of `$fillable` so a customer can never promote themselves.

## Packages for the next steps

| Package | Used for |
|---|---|
| `filament/filament` | Admin panel (catalogue, bookings, suppliers, email templates) |
| `laravel/socialite` | Google sign-in |
| `laravel/breeze` | Email sign-up, login, verification |
| `stripe/stripe-php` | Stripe Checkout sessions, webhooks, refunds |
| `barryvdh/laravel-dompdf` | Customer voucher PDFs |

## Roadmap

1. ~~Database layer~~
2. Admin panel (Filament): locations, vehicles, suppliers, routes and prices, tours, attractions, pricing rules
3. Pricing service and cart
4. Customer pages: home, transfer, tour and ticket flows, checkout
5. Stripe Checkout + webhook
6. Emails: customer voucher, supplier one-click confirm, reminders, manual send
7. Admin bookings: detail, resend, refund, activity log
