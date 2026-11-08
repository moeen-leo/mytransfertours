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

/** Customer sign-up and sign-in with email. (Google sign-in is added later.) */
class AuthController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function showLogin(): View
    {
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'That email and password don\'t match.']);
        }

        RateLimiter::clear($key);
        $this->afterLogin($request);

        return redirect()->intended(route('home'))->with('status', 'Welcome back, '.$request->user()->name.'.');
    }

    public function showRegister(): View
    {
        return view('site.auth.register');
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

        Auth::login($user, true);
        $this->afterLogin($request);

        return redirect()->intended(route('home'))->with('status', 'Your account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function afterLogin(Request $request): void
    {
        $this->cart->mergeGuestCartInto($request->user());
        $request->session()->regenerate();
    }
}
