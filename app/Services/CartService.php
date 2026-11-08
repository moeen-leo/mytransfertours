<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "My trip". Guests get a cart tied to a random token kept in their session
 * (session ids change at login; the token survives), signed-in customers a
 * cart tied to their account. Logging in merges the guest cart in.
 */
class CartService
{
    private const TOKEN_KEY = 'cart_token';

    public function current(bool $create = false): ?Cart
    {
        $user = auth()->user();

        $cart = $user
            ? Cart::query()->where('user_id', $user->id)->latest('id')->first()
            : Cart::query()->whereNull('user_id')->where('session_id', $this->token())->latest('id')->first();

        if (! $cart && $create) {
            $cart = Cart::query()->create([
                'user_id' => $user?->id,
                'session_id' => $user ? null : $this->token(),
                'expires_at' => now()->addDays(14),
            ]);
        }

        return $cart;
    }

    public function count(): int
    {
        return $this->current()?->items()->count() ?? 0;
    }

    /** Adds a priced transfer quote (from TransferQuoter) to the trip. */
    public function addTransfer(array $quote): CartItem
    {
        $cart = $this->current(create: true);
        $cart->update(['expires_at' => now()->addDays(14)]);

        return $cart->items()->create([
            'product_type' => ProductType::Transfer,
            'bookable_type' => $quote['route']->getMorphClass(),
            'bookable_id' => $quote['route']->id,
            'service_date' => $quote['date']->format('Y-m-d'),
            'service_time' => $quote['time'],
            'payload' => $quote['payload'],
            'quoted_cost' => $quote['cost'],
            'quoted_price' => $quote['sell'],
            'quoted_at' => now(),
        ]);
    }

    public function remove(int $itemId): void
    {
        $this->current()?->items()->whereKey($itemId)->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->delete();
    }

    /** After login: move the guest cart's items into the customer's cart. */
    public function mergeGuestCartInto(User $user): void
    {
        $guest = Cart::query()->whereNull('user_id')->where('session_id', session(self::TOKEN_KEY))->first();
        if (! $guest) {
            return;
        }

        $target = Cart::query()->where('user_id', $user->id)->latest('id')->first();
        if (! $target) {
            $guest->update(['user_id' => $user->id, 'session_id' => null]);

            return;
        }

        $guest->items()->update(['cart_id' => $target->id]);
        $guest->delete();
    }

    private function token(): string
    {
        if (! session()->has(self::TOKEN_KEY)) {
            session([self::TOKEN_KEY => Str::random(40)]);
        }

        return session(self::TOKEN_KEY);
    }
}
