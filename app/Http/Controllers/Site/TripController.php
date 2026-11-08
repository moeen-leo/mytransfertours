<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** "My trip": the cart, re-priced every time it is shown. */
class TripController extends Controller
{
    public function __construct(private CartService $cart, private BookingService $bookings) {}

    public function show(): View
    {
        [$lines, $total] = self::pricedLines($this->cart, $this->bookings);

        return view('site.trip', ['lines' => $lines, 'total' => $total]);
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
