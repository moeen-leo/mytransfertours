<?php

namespace App\Http\Controllers\Site;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Currency;
use App\Services\BookingService;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private BookingService $bookings) {}

    public static function cardPaymentsEnabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function show(Request $request): View|RedirectResponse
    {
        [$lines, $total] = TripController::pricedLines($this->cart, $this->bookings);

        if ($lines === []) {
            return redirect()->route('trip');
        }
        if (collect($lines)->contains(fn ($l) => $l['error'])) {
            return redirect()->route('trip')->withErrors(['cart' => 'Please remove the items that are no longer available before checking out.']);
        }

        $user = $request->user();
        $creditCurrency = $user?->credit_enabled ? (Currency::forCode($user->preferred_currency) ?? Money::current()) : null;

        return view('site.checkout', [
            'lines' => $lines,
            'total' => $total,
            'user' => $user,
            'cardEnabled' => self::cardPaymentsEnabled(),
            'creditCurrency' => $creditCurrency,
            'creditTotal' => $creditCurrency ? collect($lines)->sum(fn ($l) => $creditCurrency->fromBase($l['quote']['sell'])) : null,
            'creditAvailable' => $user?->credit_enabled ? $user->creditAvailable() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $methods = [];
        if (self::cardPaymentsEnabled()) {
            $methods[] = PaymentMethod::Card->value;
        }
        if ($user?->credit_enabled) {
            $methods[] = PaymentMethod::Credit->value;
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in($methods)],
            'terms' => ['accepted'],
        ], [
            'payment_method.required' => 'Please choose how to pay.',
            'payment_method.in' => 'Card payment is not available yet. Please contact us to book.',
            'terms.accepted' => 'Please accept the booking terms.',
        ]);

        $cart = $this->cart->current();
        if (! $cart) {
            return redirect()->route('trip');
        }

        $method = PaymentMethod::from($data['payment_method']);

        try {
            $booking = $this->bookings->placeFromCart($cart, $data, $user, $method, Money::current());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        if ($method === PaymentMethod::Card) {
            // Stripe Checkout is connected in the next step; the booking waits as "Awaiting payment".
            return redirect()->route('trip')->withErrors(['payment' => 'Card payment is being connected. Your trip is saved.']);
        }

        return redirect()->to(URL::signedRoute('booking.confirmed', ['booking' => $booking->reference]));
    }

    public function confirmed(Request $request, string $booking): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $booking = Booking::query()->where('reference', $booking)->with('items.transfer')->firstOrFail();

        return view('site.confirmed', ['booking' => $booking]);
    }
}
