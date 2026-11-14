<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SampleCatalogueSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checks the settings a live site needs and prints a ✓ / ! / ✗ list.
 * Runs at the end of every post-deploy, so /_deploy/<token> shows it too.
 */
class LaunchCheck extends Command
{
    protected $signature = 'app:launch-check';

    protected $description = 'Check that this site is configured correctly (strictest on the live site)';

    private int $errors = 0;

    public function handle(): int
    {
        $live = app()->isProduction();
        $this->line('');
        $this->line('Launch check ('.($live ? 'live site' : app()->environment().': live-only checks shown as notes').')');

        // App
        $url = (string) config('app.url');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'APP_KEY is empty');
        $this->check(! config('app.debug'), 'APP_DEBUG is off', 'APP_DEBUG=true shows error details to visitors: set APP_DEBUG=false');
        $this->check(Str::startsWith($url, 'https://'), 'APP_URL uses https', "APP_URL should start with https:// (now: {$url})");
        if ($live) {
            $this->check(! Str::contains($url, ['dev.', 'staging', 'localhost']), 'APP_URL is the live address', "APP_URL looks like a test address: {$url}");
        }

        // Database, storage, cron
        try {
            DB::select('select 1');
            $this->check(true, 'Database connection works', '');
        } catch (\Throwable $e) {
            $this->check(false, '', 'Database connection failed: '.$e->getMessage());
        }
        $this->check(is_writable(storage_path('logs')) && is_writable(storage_path('app')), 'Storage folders are writable', 'storage/ is not writable');
        $beatFile = storage_path('app/schedule-heartbeat');
        $beat = is_file($beatFile) ? (int) file_get_contents($beatFile) : 0;
        $this->check($beat > 0 && time() - $beat <= 300, 'Cron job is running (last tick '.($beat ? \Illuminate\Support\Carbon::createFromTimestamp($beat)->diffForHumans() : 'never').')',
            'Cron job has not run in the last 5 minutes: check hPanel > Advanced > Cron Jobs', warnOnly: ! $live);

        // Payments
        $secret = (string) config('services.stripe.secret');
        $key = (string) config('services.stripe.key');
        $this->check($secret !== '' && $key !== '', 'Stripe keys are set', 'Stripe keys are missing (STRIPE_KEY, STRIPE_SECRET)');
        if ($live) {
            $this->check(Str::startsWith($secret, ['sk_live_', 'rk_live_']) && Str::startsWith($key, 'pk_live_'), 'Stripe is in LIVE mode', 'Stripe keys are TEST keys: put the live keys (pk_live_ / sk_live_) in .env');
        } elseif (Str::startsWith($secret, ['sk_live_', 'rk_live_'])) {
            $this->check(false, '', 'LIVE Stripe keys on a test site: use sk_test_ keys here');
        }
        $this->check(Str::startsWith((string) config('services.stripe.webhook_secret'), 'whsec_'), 'Stripe webhook secret is set', 'STRIPE_WEBHOOK_SECRET is missing: bookings would never be marked paid');

        // Email
        $this->check(filled(config('mail.mailers.smtp.password')) || config('mail.default') !== 'smtp', 'Email sending is configured', 'MAIL_PASSWORD is empty: no confirmation or supplier emails will be sent');
        if ($live) {
            $this->check(blank(config('booking.test_recipient')), 'Supplier emails go to real suppliers', 'MAIL_TEST_RECIPIENT is set: suppliers would never get their bookings. Empty it on the live site');
        } else {
            $this->check(filled(config('booking.test_recipient')), 'Supplier emails are redirected to MAIL_TEST_RECIPIENT', 'MAIL_TEST_RECIPIENT is empty: test bookings email the real suppliers', warnOnly: true);
        }

        // Business setup
        $this->check(filled(Setting::get('company_name')) && filled(Setting::get('company_address')) && filled(Setting::get('company_email')),
            'Company details for invoices are filled in', 'Company name, address or email is missing (admin > Setup > Company & invoices)', warnOnly: ! $live);
        $this->check(User::query()->where('role', UserRole::Admin)->exists(), 'An admin account exists', 'No admin account: set ADMIN_EMAIL and deploy again');
        $this->check(config('admin.path') !== 'admin', 'Admin panel has a private address', 'Admin panel is at /admin: set ADMIN_PATH to something hard to guess', warnOnly: true);
        $this->check(Currency::query()->where('is_base', true)->exists(), 'Base currency is set', 'No base currency (admin > Setup > Currencies)');
        $this->check(filled(config('services.google.client_id')), 'Google sign-in is set up', 'Google sign-in is off (GOOGLE_CLIENT_ID empty)', warnOnly: true);
        if ($live) {
            $sampleSuppliers = Supplier::query()->where('name', 'like', SampleCatalogueSeeder::SUPPLIER_PREFIX.'%')->pluck('id');
            $sampleLive = $sampleSuppliers->isNotEmpty() && (
                \App\Models\Tour::query()->whereIn('supplier_id', $sampleSuppliers)->where('is_active', true)->exists()
                || \App\Models\Attraction::query()->whereIn('supplier_id', $sampleSuppliers)->where('is_active', true)->exists()
                || \App\Models\TransferRoute::query()->whereIn('supplier_id', $sampleSuppliers)->where('is_active', true)->exists());
            $this->check(! $sampleLive, 'No sample products are on sale', 'Sample products are active on the live site: turn them off');
        }

        $this->line($this->errors ? "  {$this->errors} problem(s) to fix." : '  All essential checks passed.');

        return $this->errors && $live ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $pass, string $fail, bool $warnOnly = false): void
    {
        if ($ok) {
            $this->line("  ✓ {$pass}");
        } elseif ($warnOnly) {
            $this->line("  ! {$fail}");
        } else {
            $this->errors++;
            $this->line("  ✗ {$fail}");
        }
    }
}
