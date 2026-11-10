<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\User;
use App\Support\Money;

/**
 * Promo codes entered on "My trip". Codes are set up in admin > Pricing > Promo codes.
 * Fixed-amount codes and minimum orders are in the base currency (MYR); the discount
 * is converted into the customer's currency when the booking is made.
 */
class CouponService
{
    public function find(string $code): ?Coupon
    {
        $code = strtoupper(trim($code));

        return $code === '' ? null : Coupon::query()->whereRaw('UPPER(code) = ?', [$code])->first();
    }

    /** Why this code can't be used on a trip of $baseTotal (MYR), or null if it can. */
    public function problem(Coupon $coupon, float $baseTotal, ?string $email = null, ?User $user = null): ?string
    {
        if (! $coupon->is_active) {
            return 'This promo code is not valid.';
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return 'This promo code is not active yet.';
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return 'This promo code has expired.';
        }
        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return 'This promo code has already been used the maximum number of times.';
        }
        if ($coupon->min_order_amount !== null && $baseTotal < (float) $coupon->min_order_amount) {
            return 'This promo code needs a trip of at least '.Money::format((float) $coupon->min_order_amount).'.';
        }
        if ($coupon->max_uses_per_user !== null && $this->usesBy($coupon, $email, $user) >= $coupon->max_uses_per_user) {
            return 'You have already used this promo code.';
        }

        return null;
    }

    /** Discount in the base currency (MYR), for showing on My trip and checkout. */
    public function baseDiscount(Coupon $coupon, float $baseTotal): float
    {
        return $coupon->discountFor($baseTotal);
    }

    /** Discount on a total that is already in $currency. */
    public function discountIn(Coupon $coupon, float $total, Currency $currency): float
    {
        $discount = $coupon->type === 'percent'
            ? $total * ((float) $coupon->value / 100)
            : $currency->fromBase((float) $coupon->value);

        return round(min(max($discount, 0), $total), 2);
    }

    /**
     * The cart's code and its discount for display. A code that no longer applies
     * stays on the cart (it may apply again if items are added) but gives no discount.
     *
     * @return array{coupon: ?Coupon, discount: float, error: ?string}
     */
    public function forCart(?Cart $cart, float $baseTotal, ?User $user = null): array
    {
        $coupon = $cart?->coupon_id ? $cart->coupon : null;
        if (! $coupon) {
            return ['coupon' => null, 'discount' => 0.0, 'error' => null];
        }

        $problem = $this->problem($coupon, $baseTotal, $user?->email, $user);

        return [
            'coupon' => $coupon,
            'discount' => $problem ? 0.0 : $this->baseDiscount($coupon, $baseTotal),
            'error' => $problem,
        ];
    }

    /** Called once a booking with this code is confirmed (paid by card or booked on account). */
    public function recordUse(Booking $booking): void
    {
        if ($booking->coupon_id) {
            Coupon::query()->whereKey($booking->coupon_id)->increment('used_count');
        }
    }

    private function usesBy(Coupon $coupon, ?string $email, ?User $user): int
    {
        if (! $email && ! $user) {
            return 0;
        }

        return Booking::query()
            ->where('coupon_id', $coupon->id)
            ->whereNotIn('status', [BookingStatus::PendingPayment, BookingStatus::PaymentFailed, BookingStatus::Cancelled])
            ->where(function ($q) use ($email, $user) {
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->count();
    }
}
