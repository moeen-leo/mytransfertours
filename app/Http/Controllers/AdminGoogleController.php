<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Site\AuthController;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Sign in with Google" on the admin login page. Only signs in accounts that already
 * exist with the Admin or Staff role (added under Team > Users). It never creates
 * accounts, and customers and unknown Google accounts are refused.
 */
class AdminGoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! AuthController::googleEnabled()) {
            return $this->fail('Google sign-in isn\'t set up yet. Please use your email and password.');
        }

        $params = ['prompt' => 'select_account'];
        if ($domain = config('admin.google_domain')) {
            $params['hd'] = $domain;   // Google only offers accounts of that domain
        }

        return Socialite::driver('google')
            ->redirectUrl(route('admin.google.callback'))
            ->with($params)
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! AuthController::googleEnabled() || $request->filled('error')) {
            return $this->fail('Google sign-in was cancelled.');
        }

        try {
            $google = Socialite::driver('google')->redirectUrl(route('admin.google.callback'))->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('We couldn\'t sign you in with Google. Please try again.');
        }

        $email = Str::lower(trim((string) $google->getEmail()));
        $verified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $domain = Str::lower((string) config('admin.google_domain'));

        if ($email === '' || ! $verified) {
            return $this->fail('That Google account has no confirmed email address.');
        }
        if ($domain !== '' && ! Str::endsWith($email, '@'.$domain)) {
            return $this->fail('Only @'.$domain.' Google accounts can sign in to the admin panel.');
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $panel = Filament::getPanel('admin');

        if (! $user || ! $user->canAccessPanel($panel)) {
            logger()->warning('Admin Google sign-in refused', ['email' => $email, 'ip' => $request->ip()]);

            return $this->fail($email.' doesn\'t have admin access. Ask an admin to add you under Users.');
        }

        // An address that was already linked to a different Google account (e.g. a recycled
        // company mailbox) is not trusted automatically.
        if ($user->google_id && $user->google_id !== $google->getId()) {
            return $this->fail('This email is linked to a different Google account. Please sign in with your password.');
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar' => $user->avatar ?: $google->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        Auth::guard('admin')->login($user, true);
        $request->session()->regenerate();

        // Go back to the admin page they were trying to open, but never to a website page
        // that a customer sign-in in the same browser may have remembered.
        $intended = (string) $request->session()->pull('url.intended', '');
        $home = rtrim($panel->getUrl(), '/');

        return redirect(($intended === $home || Str::startsWith($intended, $home.'/')) ? $intended : $home);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('filament.admin.auth.login')->with('admin_google_error', $message);
    }
}
