<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** "My trip": the cart, re-priced every time it is shown. */
class TripController extends Controller
{
    public function __construct(private CartService $cart, private BookingService $bookings, private CouponService $coupons) {}

    public function show(Request $request): View
    {
        [$lines, $total] = self::pricedLines($this->cart, $this->bookings);
        $promo = $this->coupons->forCart($this->cart->current(), $total, $request->user());

        return view('site.trip', ['lines' => $lines, 'total' => $total, 'promo' => $promo]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => 'Please enter a promo code.']);

        $cart = $this->cart->current();
        if (! $cart) {
            return redirect()->route('trip');
        }

        $coupon = $this->coupons->find((string) $request->input('code'));
        if (! $coupon) {
            return redirect()->route('trip')->withErrors(['code' => 'We don\'t recognise that promo code.'])->withInput();
        }

        [, $total] = self::pricedLines($this->cart, $this->bookings);
        if ($problem = $this->coupons->problem($coupon, $total, $request->user()?->email, $request->user())) {
            return redirect()->route('trip')->withErrors(['code' => $problem])->withInput();
        }

        $cart->update(['coupon_id' => $coupon->id]);

        return redirect()->route('trip')->with('status', 'Promo code '.$coupon->code.' applied.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->current()?->update(['coupon_id' => null]);

        return redirect()->route('trip')->with('status', 'Promo code removed.');
    }

    public function remove(int $item): RedirectResponse
    {
        $this->cart->remove($item);

        return redirect()->route('trip')->with('status', 'Removed from your trip.');
    }

    /**
     * Cart items with fresh prices. Items that can no longer be booked are kept
     * (so the customer sees them) but flagged with the reason.
     *
     * @return array{0: array<int, array{item: \App\Models\CartItem, quote: ?array, error: ?string}>, 1: float}
     */
    public static function pricedLines(CartService $cart, BookingService $bookings): array
    {
        $current = $cart->current();
        $lines = [];
        $total = 0.0;

        foreach ($current?->items()->get() ?? [] as $item) {
            try {
                $quote = $bookings->quoteItem($item);
                $total += $quote['sell'];
                $lines[] = ['item' => $item, 'quote' => $quote, 'error' => null];
            } catch (ValidationException $e) {
                $lines[] = ['item' => $item, 'quote' => null, 'error' => collect($e->errors())->flatten()->first()];
            }
        }

        return [$lines, round($total, 2)];
    }
}
