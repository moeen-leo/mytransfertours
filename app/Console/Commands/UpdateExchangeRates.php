<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes exchange rates from the European Central Bank reference rates
 * (published once a day, ~16:00 CET) via the free Frankfurter API.
 *
 * RM (MYR) is the base: we store "1 MYR = x currency". Only currencies with
 * auto_update on are touched. If the service is unreachable nothing changes,
 * so prices keep using the last known rate.
 *
 * Runs from the scheduler on every cron tick but only fetches when the
 * rates are older than 12 hours (Hostinger's cron is hourly at best).
 */
class UpdateExchangeRates extends Command
{
    protected $signature = 'app:update-exchange-rates {--force : Fetch even if rates were updated recently}';

    protected $description = 'Update exchange rates (base MYR) from the European Central Bank';

    private const STALE_AFTER_HOURS = 12;

    public function handle(): int
    {
        $base = Currency::base();
        $targets = Currency::query()->where('is_base', false)->where('auto_update', true)->get();

        if ($targets->isEmpty()) {
            $this->line('No currencies set to auto-update.');

            return self::SUCCESS;
        }

        $fresh = $targets->every(fn (Currency $c) => $c->rate_source === 'ecb'
            && $c->rate_updated_at?->gt(now()->subHours(self::STALE_AFTER_HOURS)));

        if ($fresh && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $rates = $this->fetch($base, $targets->pluck('code')->all());

        if ($rates === []) {
            $this->warn('Exchange-rate service unreachable; keeping the current rates.');
            Log::warning('Exchange-rate update failed; kept existing rates.');

            return self::FAILURE;
        }

        foreach ($targets as $currency) {
            $rate = $rates[$currency->code] ?? null;

            if (! is_numeric($rate) || $rate <= 0) {
                $this->warn("No rate for {$currency->code}; kept {$currency->rate}.");

                continue;
            }

            // Guard against a bad feed: ignore a jump of more than 15 % in one go.
            $old = (float) $currency->rate;
            if ($old > 0 && $currency->rate_source === 'ecb' && abs($rate - $old) / $old > 0.15) {
                $this->warn("{$currency->code}: new rate {$rate} differs >15% from {$old}; not applied. Check it manually.");
                Log::warning('Exchange-rate jump ignored', ['code' => $currency->code, 'old' => $old, 'new' => $rate]);

                continue;
            }

            $currency->forceFill(['rate' => round((float) $rate, 6), 'rate_source' => 'ecb'])->save();
            $this->info("1 {$base} = {$currency->rate} {$currency->code}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, float> code => rate, empty on failure */
    private function fetch(string $base, array $codes): array
    {
        $symbols = implode(',', $codes);

        // Primary: Frankfurter v1 -> {"base":"MYR","date":"…","rates":{"USD":0.23,…}}
        foreach (['https://api.frankfurter.dev/v1/latest', 'https://api.frankfurter.app/latest'] as $url) {
            try {
                $response = Http::timeout(8)->retry(1, 300, throw: false)->acceptJson()
                    ->get($url, ['base' => $base, 'symbols' => $symbols]);

                if ($response->successful() && is_array($response->json('rates'))) {
                    return array_map('floatval', $response->json('rates'));
                }
            } catch (\Throwable $e) {
                Log::info('Frankfurter v1 request failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        // Fallback: Frankfurter v2, one pair at a time -> {"rate":0.23,…}
        $rates = [];
        foreach ($codes as $code) {
            try {
                $response = Http::timeout(6)->acceptJson()->get("https://api.frankfurter.dev/v2/rate/{$base}/{$code}");
                if ($response->successful() && is_numeric($response->json('rate'))) {
                    $rates[$code] = (float) $response->json('rate');
                }
            } catch (\Throwable $e) {
                Log::info('Frankfurter v2 request failed', ['code' => $code, 'error' => $e->getMessage()]);
            }
        }

        return $rates;
    }
}
