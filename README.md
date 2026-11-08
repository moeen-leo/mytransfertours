# MyTransferTours

Transfers, tours and attraction tickets in one cart, paid once with Stripe Checkout. Malaysia first, built to go global.

Laravel 12 · PHP 8.2+ · MySQL 8 / MariaDB 10.6+

## Docs

- [`docs/DATABASE.md`](docs/DATABASE.md): database design, tables and the booking automation flow
- [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md): deploying to Hostinger without SSH

## Branches

| Branch | Purpose |
|---|---|
| `develop` | Day-to-day work. Every push is built by GitHub Actions. |
| `build-dev` | Ready-to-run build of `develop` (includes `vendor/`). **Hostinger dev site deploys this.** |
| `main` | Live code. Updated by merging `develop` after testing on dev. |
| `build-main` | Ready-to-run build of `main`. **Hostinger live site deploys this** (at launch). |

Never commit to `build-*` branches; they are overwritten on every build.

## Run locally

```bash
composer install
cp .env.example .env    # set APP_ENV=local, APP_DEBUG=true and your local DB
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Packages for the next steps

| Package | Used for |
|---|---|
| `filament/filament` | Admin panel (catalogue, bookings, suppliers, email templates) |
| `laravel/socialite` | Google sign-in |
| `stripe/stripe-php` | Stripe Checkout sessions, webhooks, refunds |
| `barryvdh/laravel-dompdf` | Customer voucher PDFs |

## Roadmap

1. ~~Database layer~~
2. ~~Laravel base + Hostinger auto-deploy~~
3. Admin panel (Filament): locations, vehicles, suppliers, routes and prices, tours, attractions, pricing rules
4. Pricing service and cart
5. Customer pages: home, transfer, tour and ticket flows, checkout
6. Sign-up with email and Google
7. Stripe Checkout + webhook
8. Emails: customer voucher, supplier one-click confirm, reminders, manual send
9. Admin bookings: detail, resend, refund, activity log
