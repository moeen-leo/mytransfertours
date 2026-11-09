<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes exchange rates. RM (MYR) is the base: we store "1 MYR = x currency".
 *
 * - Currencies the European Central Bank publishes (USD, EUR, GBP, SGD, THB, INR...) come
 *   from the ECB reference rates via the free Frankfurter API, within an hour of each
 *   publication (weekdays ~16:00 CET).
 * - Others (e.g. PKR, AED, SAR, VND) come from ExchangeRate-API's free open endpoint once a
 *   day (attribution shown in the site footer), with fawazahmed0's currency-api as a backup.
 *
 * Only currencies with auto_update on are touched. If a service is unreachable nothing
 * changes, so prices keep using the last known rate.
 */
class UpdateExchangeRates extends Command
{
    protected $signature = 'app:update-exchange-rates {--force : Fetch even if rates were updated recently}';

    protected $description = 'Update exchange rates (base MYR): ECB for major currencies, ExchangeRate-API for the rest';

    /** The most recent ECB publication time we should have: last weekday at 16:30 Frankfurt time. */
    public static function lastPublication(): \Carbon\CarbonImmutable
    {
        $t = now('Europe/Berlin')->toImmutable()->setTime(16, 30);
        if ($t->isFuture()) {
            $t = $t->subDay();
        }
        while ($t->isWeekend()) {
            $t = $t->subDay();
        }

        return $t;
    }

    /** Currencies in the ECB daily reference rates. */
    public const ECB = ['AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR'];

    /** Source names stored in currencies.rate_source. */
    public const DAILY_SOURCES = ['er-api', 'currency-api'];

    public function handle(): int
    {
        $base = Currency::base();
        $targets = Currency::query()->where('is_base', false)->where('auto_update', true)->get();
        if ($targets->isEmpty()) {
            $this->line('No currencies set to auto-update.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $since = self::lastPublication();
        $dayAgo = now()->subHours(20);

        [$ecb, $other] = $targets->partition(fn (Currency $c) => in_array($c->code, self::ECB, true) && in_array($base, self::ECB, true));

        $staleEcb = $ecb->reject(fn (Currency $c) => ! $force && $c->rate_source === 'ecb' && $c->rate_updated_at?->gte($since));
        $staleOther = $other->reject(fn (Currency $c) => ! $force && in_array($c->rate_source, self::DAILY_SOURCES, true) && $c->rate_updated_at?->gte($dayAgo));

        // A failed daily fetch is retried hourly, not every minute.
        if ($staleOther->isNotEmpty() && ! $force && ! Cache::add('fx:daily-attempt', 1, now()->addHour())) {
            $staleOther = collect();
        }

        if ($staleEcb->isEmpty() && $staleOther->isEmpty()) {
            return self::SUCCESS;
        }

        $failed = false;

        if ($staleEcb->isNotEmpty()) {
            $rates = $this->fetchEcb($base, $staleEcb->pluck('code')->all());
            $failed = $rates === [] || $failed;
            $this->apply($base, $staleEcb, $rates, 'ecb');
        }

        if ($staleOther->isNotEmpty()) {
            [$source, $rates] = $this->fetchDaily($base);
            $failed = $rates === [] || $failed;
            $this->apply($base, $staleOther, $rates, $source);
        }

        if ($failed) {
            $this->warn('An exchange-rate service was unreachable; kept the current rates where needed.');
            Log::warning('Exchange-rate update partly failed; kept existing rates.');
        }

        return self::SUCCESS;
    }

    private function apply(string $base, $currencies, array $rates, string $source): void
    {
        foreach ($currencies as $currency) {
            $rate = $rates[$currency->code] ?? null;
            if (! is_numeric($rate) || $rate <= 0) {
                $this->warn("No rate for {$currency->code} from {$source}; kept {$currency->rate}.");

                continue;
            }

            // Guard against a bad feed: ignore a jump of more than 15 % from the same source.
            $old = (float) $currency->rate;
            if ($old > 0 && $currency->rate_source === $source && abs($rate - $old) / $old > 0.15) {
                $this->warn("{$currency->code}: new rate {$rate} differs >15% from {$old}; not applied. Check it manually.");
                Log::warning('Exchange-rate jump ignored', ['code' => $currency->code, 'old' => $old, 'new' => $rate, 'source' => $source]);

                continue;
            }

            $currency->forceFill(['rate' => round((float) $rate, 6), 'rate_source' => $source, 'rate_updated_at' => now()])->save();
            $this->info("1 {$base} = {$currency->rate} {$currency->code} ({$source})");
        }
    }

    /** @return array<string, float> code => rate, empty on failure */
    private function fetchEcb(string $base, array $codes): array
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

    /** @return array{0: string, 1: array<string, float>} [source, code => rate] */
    private function fetchDaily(string $base): array
    {
        // ExchangeRate-API open access (no key, daily): {"result":"success","rates":{"PKR":68.1,…}}
        try {
            $response = Http::timeout(8)->retry(1, 500, throw: false)->acceptJson()->get('https://open.er-api.com/v6/latest/'.$base);
            if ($response->successful() && $response->json('result') === 'success' && is_array($response->json('rates'))) {
                return ['er-api', array_map('floatval', $response->json('rates'))];
            }
        } catch (\Throwable $e) {
            Log::info('ExchangeRate-API request failed', ['error' => $e->getMessage()]);
        }

        // Backup: fawazahmed0 currency-api on jsDelivr: {"date":"…","myr":{"pkr":68.1,…}}
        $key = strtolower($base);
        foreach (["https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$key}.json", "https://latest.currency-api.pages.dev/v1/currencies/{$key}.json"] as $url) {
            try {
                $response = Http::timeout(8)->acceptJson()->get($url);
                if ($response->successful() && is_array($response->json($key))) {
                    $rates = [];
                    foreach ($response->json($key) as $code => $rate) {
                        $rates[strtoupper($code)] = (float) $rate;
                    }

                    return ['currency-api', $rates];
                }
            } catch (\Throwable $e) {
                Log::info('currency-api request failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return ['er-api', []];
    }
}
