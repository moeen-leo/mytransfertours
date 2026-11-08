<?php

namespace App\Http\Controllers\Site;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Currency;
use App\Services\BookingService;
use App\Services\CartService;
use App\Services\StripeService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private BookingService $bookings, private StripeService $stripe) {}

    public static function cardPaymentsEnabled(): bool
    {
        return StripeService::enabled();
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
            // The trip stays in the cart until Stripe confirms payment.
            try {
                $url = $this->stripe->createCheckout(
                    $booking,
                    URL::signedRoute('checkout.success', ['booking' => $booking->reference]).'&session_id={CHECKOUT_SESSION_ID}',
                    URL::signedRoute('checkout.cancelled', ['booking' => $booking->reference]),
                );
            } catch (\Throwable $e) {
                report($e);
                $this->stripe->markAbandoned($booking, 'Could not start Stripe Checkout: '.$e->getMessage());

                return back()->withInput()->withErrors(['payment' => 'We couldn\'t start the card payment. Please try again in a moment.']);
            }

            return redirect()->away($url);
        }

        return redirect()->to(URL::signedRoute('booking.confirmed', ['booking' => $booking->reference]));
    }

    /** Stripe sends the customer back here after paying. The webhook confirms too; whichever is first wins. */
    public function success(Request $request, string $booking): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['session_id']), 403);

        $model = Booking::query()->where('reference', $booking)->firstOrFail();
        $sessionId = (string) $request->query('session_id');

        if ($sessionId !== '' && $sessionId === $model->stripe_checkout_session_id) {
            try {
                $this->stripe->markPaidFromSession($this->stripe->retrieveSession($sessionId));
            } catch (\Throwable $e) {
                report($e);   // the webhook will still mark it paid
            }
        }

        if ($cart = $this->cart->current()) {
            $this->cart->clear($cart);
        }

        return redirect()->to(URL::signedRoute('booking.confirmed', ['booking' => $model->reference]));
    }

    /** Customer pressed "back" on Stripe's page. Their trip is still in the cart. */
    public function cancelled(Request $request, string $booking): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $model = Booking::query()->where('reference', $booking)->firstOrFail();
        $this->stripe->markAbandoned($model, 'Customer cancelled on the Stripe page');

        return redirect()->route('checkout')->withErrors(['payment' => 'Payment was cancelled. Your trip is still here; you can try again.']);
    }

    public function confirmed(Request $request, string $booking): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $booking = Booking::query()->where('reference', $booking)->with('items.transfer')->firstOrFail();

        return view('site.confirmed', ['booking' => $booking]);
    }
}
