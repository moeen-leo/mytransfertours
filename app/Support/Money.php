<?php

namespace App\Support;

use App\Models\Currency;

/**
 * The visitor's display currency and MYR -> currency conversion.
 * All catalogue prices are stored in MYR; this is the only place they are converted.
 */
class Money
{
    public const SESSION_KEY = 'currency';

    public static function current(): Currency
    {
        $code = session(self::SESSION_KEY)
            ?? auth()->user()?->preferred_currency
            ?? Currency::base();

        return Currency::forCode((string) $code)
            ?? Currency::forCode(Currency::base())
            ?? new Currency(['code' => 'MYR', 'symbol' => 'RM', 'rate' => 1, 'buffer_percent' => 0, 'is_base' => true]);
    }

    public static function set(string $code): bool
    {
        if (! Currency::forCode($code)) {
            return false;
        }
        session([self::SESSION_KEY => strtoupper($code)]);

        return true;
    }

    /** Converts an MYR amount to the given (or current) currency, rounded to cents. */
    public static function convert(float $myr, ?Currency $currency = null): float
    {
        return ($currency ?? static::current())->fromBase($myr);
    }

    /** "RM 140.00", "$35.53" … from an MYR amount. */
    public static function format(float $myr, ?Currency $currency = null): string
    {
        $currency ??= static::current();

        return $currency->format($currency->fromBase($myr));
    }

    /** Formats an amount that is already in $code (e.g. a booking total). */
    public static function formatIn(float $amount, string $code): string
    {
        $currency = Currency::forCode($code);

        return $currency ? $currency->format($amount) : $code.' '.number_format($amount, 2);
    }
}
