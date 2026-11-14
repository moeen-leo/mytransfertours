<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails your team when the live site hits an unexpected error. The same error is
 * emailed at most once per 30 minutes, and never more than 10 alerts an hour.
 */
class ErrorAlert
{
    private static bool $sending = false;

    public static function report(Throwable $e): void
    {
        if (self::$sending || ! app()->isProduction()) {
            return;
        }
        self::$sending = true;

        try {
            $fingerprint = 'error-alert:'.sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
            if (! Cache::add($fingerprint, 1, now()->addMinutes(30))) {
                return;
            }
            $hourKey = 'error-alert:count:'.now()->format('YmdH');
            Cache::add($hourKey, 0, now()->addHour());
            if (Cache::increment($hourKey) > 10) {
                return;
            }

            $to = Setting::get('company_email') ?: User::query()->where('role', UserRole::Admin)->value('email');
            if (! $to) {
                return;
            }

            $request = app()->runningInConsole() ? null : request();
            $body = implode("\n", array_filter([
                'The live site hit an error. Details are also in storage/logs.',
                '',
                'Error:   '.get_class($e).': '.$e->getMessage(),
                'Where:   '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine(),
                $request ? 'Page:    '.$request->method().' '.$request->fullUrl() : 'Page:    (scheduled task / command)',
                'Time:    '.now('Asia/Kuala_Lumpur')->format('j M Y H:i').' (Malaysia)',
                '',
                'Repeats of this error are not emailed again for 30 minutes.',
            ], fn ($l) => $l !== null));

            Mail::raw($body, fn ($m) => $m->to($to)->subject('[MyTransferTours] Site error: '.str($e->getMessage())->limit(70)));
        } catch (Throwable) {
            // An alert must never cause another error.
        } finally {
            self::$sending = false;
        }
    }
}
