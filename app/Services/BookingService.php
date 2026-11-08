<?php

namespace App\Services;

use App\Enums\BookingItemStatus;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Booking;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns "My trip" into a booking. Every item is re-priced first, so a customer
 * always pays today's price, and nothing is booked if any item has become
 * unavailable. Supplier cost is stored in MYR; customer prices in the chosen
 * currency, with the rate recorded.
 */
class BookingService
{
    public function __construct(private TransferQuoter $transfers, private CartService $cart, private BookingMailer $mailer) {}

    /**
     * Re-prices every item in the cart.
     *
     * @return array<int, array{item: CartItem, quote: array}>
     */
    public function quoteCart(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->items()->get() as $item) {
            $lines[] = ['item' => $item, 'quote' => $this->quoteItem($item)];
        }

        return $lines;
    }

    public function quoteItem(CartItem $item): array
    {
        return match ($item->product_type) {
            ProductType::Transfer => $this->transfers->quote($item->payload),
            default => throw ValidationException::withMessages(['cart' => 'This item type can\'t be booked yet.']),
        };
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone?: ?string, country_code?: ?string, notes?: ?string}  $lead
     */
    public function placeFromCart(Cart $cart, array $lead, ?User $user, PaymentMethod $method, Currency $currency): Booking
    {
        $lines = $this->quoteCart($cart);

        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Your trip is empty.']);
        }

        // Credit customers are invoiced in their account currency, and must have room on their limit.
        if ($method === PaymentMethod::Credit) {
            if (! $user?->credit_enabled) {
                throw ValidationException::withMessages(['payment' => 'Pay on account is not enabled for your account.']);
            }
            $currency = Currency::forCode($user->preferred_currency) ?? $currency;
        }

        $rate = $currency->effectiveRate();
        $itemTotals = array_map(fn ($l) => $currency->fromBase($l['quote']['sell']), $lines);
        $total = round(array_sum($itemTotals), 2);
        $costTotal = round(array_sum(array_map(fn ($l) => $l['quote']['cost'], $lines)), 2);

        if ($method === PaymentMethod::Credit && ! $user->canBookOnCredit($total)) {
            $available = $user->creditAvailable();
            throw ValidationException::withMessages(['payment' => 'This trip ('.$currency->format($total).') is more than your available credit ('
                .$currency->format((float) $available).'). Please pay by card or contact us to raise your limit.']);
        }

        $booking = DB::transaction(function () use ($lines, $itemTotals, $lead, $user, $method, $currency, $rate, $total, $costTotal, $cart) {
            $booking = new Booking;
            $booking->forceFill([
                'user_id' => $user?->id,
                'lead_first_name' => $lead['first_name'],
                'lead_last_name' => $lead['last_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'] ?? null,
                'country_code' => $lead['country_code'] ?? null,
                'customer_notes' => $lead['notes'] ?? null,
                'currency' => $currency->code,
                'exchange_rate' => $rate,
                'subtotal' => $total,
                'discount_total' => 0,
                'total' => $total,
                'cost_total' => $costTotal,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Unpaid,
                'status' => $method === PaymentMethod::Credit ? BookingStatus::Booked : BookingStatus::PendingPayment,
                'payment_due_at' => $method === PaymentMethod::Credit ? today()->addDays((int) $user->credit_terms_days) : null,
                'ip_address' => request()->ip(),
                'locale' => app()->getLocale(),
            ]);
            $booking->snapshotBilling($user);
            $booking->save();

            $counters = [];
            foreach ($lines as $i => $line) {
                $quote = $line['quote'];
                $type = $line['item']->product_type;
                $letter = $type->referenceLetter();
                $counters[$letter] = ($counters[$letter] ?? 0) + 1;

                $item = $booking->items()->create([
                    'reference' => $booking->reference.'-'.$letter.$counters[$letter],
                    'product_type' => $type,
                    'bookable_type' => $quote['route']->getMorphClass(),
                    'bookable_id' => $quote['route']->id,
                    'title' => $quote['title'],
                    'service_date' => $quote['date']->format('Y-m-d'),
                    'service_time' => $quote['time'],
                    'supplier_id' => $quote['supplier']?->id,
                    'supplier_name' => $quote['supplier']?->name ?? '—',
                    'supplier_email' => $quote['supplier']?->email ?? '',
                    'cost_total' => $quote['cost'],
                    'selling_total' => $itemTotals[$i],
                    'status' => BookingItemStatus::Pending,
                    'cancellation_deadline' => $quote['date']->setTimeFromTimeString($quote['time'])->subHours((int) $quote['route']->cancellation_hours),
                ]);

                if ($type === ProductType::Transfer) {
                    $p = $quote['payload'];
                    $item->transfer()->create([
                        'pickup_location_id' => $quote['from']->id,
                        'dropoff_location_id' => $quote['to']->id,
                        'pickup_location_name' => $quote['from']->name,
                        'dropoff_location_name' => $quote['to']->name,
                        'pickup_address' => $p['pickup_address'],
                        'dropoff_address' => $p['dropoff_address'],
                        'vehicle_type_id' => $quote['vehicle']->id,
                        'vehicle_type_name' => $quote['vehicle']->name,
                        'flight_number' => $p['flight_number'],
                        'adults' => $p['adults'],
                        'children' => $p['children'],
                        'infants' => $p['infants'],
                        'bags' => $p['bags'],
                        'sign_name' => $p['sign_name'] ?: strtoupper($lead['first_name'].' '.$lead['last_name']),
                        'driver_notes' => $p['driver_notes'],
                    ]);
                }

                foreach ($quote['extras'] as $extraLine) {
                    $item->extras()->create([
                        'extra_id' => $extraLine['extra']->id,
                        'name' => $extraLine['extra']->name,
                        'quantity' => $extraLine['qty'],
                        'unit_cost' => $extraLine['extra']->cost_price,
                        'unit_price' => $currency->fromBase((float) $extraLine['extra']->selling_price),
                    ]);
                }
            }

            $booking->log('created', $method === PaymentMethod::Credit
                ? 'Booked on account by '.$user->email
                : 'Created, waiting for card payment', [], null, $user?->id);

            if ($method === PaymentMethod::Credit) {
                $booking->assignInvoiceNumber();
                $this->cart->clear($cart);
            }

            return $booking->fresh(['items']);
        });

        // On account = booked straight away: email the customer and every supplier.
        // (Card bookings are emailed once Stripe confirms payment.)
        if ($method === PaymentMethod::Credit) {
            $this->mailer->bookingPlaced($booking);
        }

        return $booking;
    }
}
