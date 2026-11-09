<?php

namespace App\Http\Controllers\Site;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

/** Customer sign-up and sign-in with email or Google. */
class AuthController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function showLogin(): View
    {
        $this->rememberWhereTheyCameFrom();

        return view('site.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            $googleOnly = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])
                ->whereNull('password')->whereNotNull('google_id')->exists();

            throw ValidationException::withMessages(['email' => $googleOnly
                ? 'This account signs in with Google. Use the "Continue with Google" button.'
                : 'That email and password don\'t match.']);
        }

        RateLimiter::clear($key);
        $this->afterLogin($request);

        return redirect()->intended(route('home'))->with('status', 'Welcome back, '.$request->user()->name.'.');
    }

    public function showRegister(): View
    {
        $this->rememberWhereTheyCameFrom();

        return view('site.auth.register');
    }

    /** After signing in from e.g. checkout, send them back there instead of the home page. */
    private function rememberWhereTheyCameFrom(): void
    {
        $previous = url()->previous();
        $site = rtrim(url('/'), '/');

        if (session()->has('url.intended') || ! ($previous === $site || Str::startsWith($previous, $site.'/'))) {
            return;
        }

        $path = '/'.ltrim(Str::after($previous, $site), '/');
        if (! Str::startsWith($path, ['/login', '/register', '/auth/', '/logout', '/email/'])) {
            session()->put('url.intended', $previous);
        }
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ], ['email.unique' => 'An account with this email already exists. Please sign in.']);

        $user = new User;
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'preferred_currency' => Money::current()->code,
        ]);
        $user->role = UserRole::Customer;
        $user->save();

        try {
            event(new \Illuminate\Auth\Events\Registered($user));   // sends the "confirm your email" message
        } catch (\Throwable $e) {
            report($e);   // a mail problem must never block sign-up; they can resend later
        }
        Auth::guard('web')->login($user, true);
        $this->afterLogin($request);

        return redirect()->intended(route('home'))->with('status', 'Your account is ready. We\'ve emailed you a link to confirm your address.');
    }

    /** True when the Google OAuth client is set in .env; the buttons are hidden otherwise. */
    public static function googleEnabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (! self::googleEnabled()) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in isn\'t available yet. Please use your email.']);
        }

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if (! self::googleEnabled() || $request->filled('error')) {
            // e.g. the customer pressed Cancel on Google's screen
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled.']);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);   // expired/replayed state, network trouble, wrong client secret...

            return redirect()->route('login')->withErrors(['email' => 'We couldn\'t sign you in with Google. Please try again.']);
        }

        $email = Str::lower(trim((string) $google->getEmail()));
        $emailVerified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || ! $emailVerified) {
            return redirect()->route('login')->withErrors(['email' => 'Your Google account has no confirmed email address. Please sign up with your email instead.']);
        }

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        $isNew = $user === null;

        if ($isNew) {
            $user = new User;
            $user->fill([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'preferred_currency' => Money::current()->code,
            ]);
            $user->role = UserRole::Customer;
        }

        // Google has confirmed the address, so linking an existing email account is safe,
        // and it also counts as email verification.
        $user->google_id = $google->getId();
        if (blank($user->avatar) && $google->getAvatar()) {
            $user->avatar = $google->getAvatar();
        }
        if ($user->email_verified_at === null && Str::lower($user->email) === $email) {
            $user->email_verified_at = now();
        }
        $user->save();

        Auth::guard('web')->login($user, true);
        $this->afterLogin($request);

        return redirect()->intended(route('home'))->with('status', $isNew
            ? 'Welcome, '.$user->name.'. Your account is ready.'
            : 'Welcome back, '.$user->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Only the website login; an admin session in the same browser stays signed in.
        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function afterLogin(Request $request): void
    {
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->cart->mergeGuestCartInto($request->user());
        $request->user()->claimGuestBookings();   // only if their email is confirmed
        $request->session()->regenerate();
    }

    public function verifyNotice(): View
    {
        return view('site.auth.verify');
    }

    public function verify(\Illuminate\Foundation\Auth\EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();   // fires Verified -> earlier guest bookings are linked

        return redirect()->route('account.bookings')->with('status', 'Email confirmed. Any bookings you made with it are now in your account.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['email' => 'We couldn\'t send the email just now. Please try again later.']);
            }
        }

        return back()->with('status', 'We\'ve sent you a new confirmation link.');
    }
}
