# MyTransferTours

Transfers, tours and attraction tickets in one cart, paid once with Stripe Checkout. Malaysia first, built to go global.

Laravel 12 · PHP 8.2+ · MySQL 8 / MariaDB 10.6+

## Docs

- [`docs/DATABASE.md`](docs/DATABASE.md): database design, tables and the booking automation flow
- [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md): deploying to Hostinger without SSH
- [`docs/LAUNCH.md`](docs/LAUNCH.md): step-by-step checklist for going live

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
| `laravel/socialite` | Google sign-in |
| `stripe/stripe-php` | Stripe Checkout sessions, webhooks, refunds |
| `barryvdh/laravel-dompdf` | Customer voucher PDFs |

## Roadmap

1. ~~Database layer~~
2. ~~Laravel base + Hostinger auto-deploy~~
3. ~~Admin panel (Filament): locations, vehicles, suppliers, routes and prices, tours, attractions, add-ons, pricing rules, promo codes, team~~
4. Pricing service and cart
5. Customer pages: home, transfer, tour and ticket flows, checkout
6. ~~Sign-up with email and Google~~ (Google setup: see [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md#google-sign-in))
7. Stripe Checkout + webhook
8. Emails: customer voucher, supplier one-click confirm, reminders, manual send
9. Admin bookings: detail, resend, refund, activity log
10. ~~Admin dashboard: sales, profit (admins only), suppliers awaiting confirmation, unpaid on account, refunds, best sellers~~
11. ~~SEO basics: page titles and descriptions, canonical links, share previews (`public/og-image.png`), `/sitemap.xml` and `/robots.txt` (dev is always hidden from search engines; only the live site with `APP_ENV=production` is indexed)~~
12. ~~Sample data (dev only): admin dashboard → **Sample data → Load sample catalogue** adds demo transfers, tours, tickets and add-ons (`database/seeders/SampleCatalogueSeeder.php`); **Hide sample catalogue** turns them off again~~
13. ~~Launch prep: launch check after every deploy, error alert emails, daily database backups, [`docs/LAUNCH.md`](docs/LAUNCH.md)~~
14. ~~Information pages: About us, Contact, FAQ, Terms, Cancellation and refund policy, Privacy policy (admin → Setup → Website pages; generic template text, have it checked for your business)~~
