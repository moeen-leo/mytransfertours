<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Models\Attraction;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\VehicleType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The customer's booking voucher as a PDF: guest, booking reference, and every
 * detail we hold for each service (date, people, vehicle and capacity, pick-up,
 * tickets, add-ons, cancellation terms). No prices: it is shown to drivers and guides.
 */
class VoucherPdf
{
    /** How card refunds reach the customer (Stripe: 5-10 days). */
    public const REFUND_NOTE = 'Refunds go back to the card you paid with and take 5–10 days to appear on your statement.';

    /** Vouchers exist once a booking is paid or confirmed on account. */
    public static function available(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Booked, BookingStatus::Confirmed, BookingStatus::Completed], true);
    }

    public function filename(Booking $booking): string
    {
        return 'Voucher-'.$booking->reference.'.pdf';
    }

    /** The PDF file contents. */
    public function render(Booking $booking): string
    {
        return Pdf::loadView('vouchers.booking', $this->data($booking))
            ->setPaper('a4')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->output();
    }

    public function data(Booking $booking): array
    {
        $booking->loadMissing([
            'items' => fn ($q) => $q->orderBy('service_date')->orderBy('service_time'),
            'items.bookable', 'items.transfer.vehicleType', 'items.tour.vehicleType', 'items.tickets.ticketType', 'items.extras',
        ]);

        return [
            'booking' => $booking,
            'items' => $booking->items->map(fn (BookingItem $item) => $this->item($item))->all(),
            'company' => [
                'name' => Setting::get('company_name') ?: 'MyTransferTours',
                'email' => Setting::get('company_email') ?: config('mail.from.address'),
                'phone' => Setting::get('company_phone', ''),
            ],
            'logo' => 'data:image/svg+xml;base64,'.base64_encode((string) @file_get_contents(public_path('favicon.svg'))),
        ];
    }

    private function item(BookingItem $item): array
    {
        $product = $item->bookable;
        $t = $item->transfer;
        $tour = $item->tour;
        $vehicle = $t?->vehicleType ?? $tour?->vehicleType;

        $image = null;
        $description = null;
        $details = [];

        if ($product instanceof Tour) {
            $image = $product->images[0] ?? null;
            $description = $product->summary ?: Str::limit(trim(strip_tags(Str::markdown((string) $product->description))), 400);
            if ($product->duration_minutes) {
                $details['Duration'] = self::duration($product->duration_minutes);
            }
        } elseif ($product instanceof Attraction) {
            $image = $product->images[0] ?? null;
            $description = $product->summary ?: Str::limit(trim(strip_tags(Str::markdown((string) $product->description))), 400);
        } elseif ($vehicle) {
            $image = $vehicle->image;
            $description = $vehicle->description;
        }

        return [
            'model' => $item,
            'image' => $this->imageData($image),
            'description' => $description,
            'details' => $details,
            'vehicle' => $vehicle,
            'capacity' => $vehicle instanceof VehicleType ? 'Up to '.$vehicle->max_pax.' passengers'.($vehicle->max_bags ? ' and '.$vehicle->max_bags.' large bags' : '') : null,
            'tour' => $product instanceof Tour ? $product : null,
            'attraction' => $product instanceof Attraction ? $product : null,
            'route' => $item->product_type === ProductType::Transfer ? $product : null,
            'cancellation' => $this->cancellation($item, $product),
        ];
    }

    private function cancellation(BookingItem $item, mixed $product): string
    {
        if ($product instanceof Attraction && ! $product->is_refundable) {
            return 'Non-refundable: these tickets cannot be cancelled or changed.';
        }

        if ($item->cancellation_deadline) {
            return 'Free cancellation until '.$item->cancellation_deadline->format('D j M Y, H:i')
                .'. Cancellations after that time, or not turning up, are not refunded. '
                .self::REFUND_NOTE;
        }

        return 'See our cancellation policy at '.route('page', 'cancellation-policy').'. '.self::REFUND_NOTE;
    }

    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? $h.' h ' : '').($m ? $m.' min' : ''));
    }

    /**
     * An uploaded image as a small JPEG data URI (dompdf can't read the uploads
     * folder, and full-size photos would make the PDF huge). Null if unusable.
     */
    private function imageData(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            $disk = Storage::disk('uploads');
            if (! $disk->exists($path)) {
                return null;
            }
            $raw = $disk->get($path);

            if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($raw))) {
                $w = imagesx($img);
                $h = imagesy($img);
                $nw = min(640, $w);
                $nh = (int) round($h * $nw / max(1, $w));
                $small = imagecreatetruecolor($nw, $nh);
                imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
                imagecopyresampled($small, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                imagejpeg($small, null, 78);
                $jpeg = (string) ob_get_clean();

                return 'data:image/jpeg;base64,'.base64_encode($jpeg);
            }

            // No GD: only small JPEG/PNG files are embedded as they are.
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (strlen($raw) < 600_000 && in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                return 'data:image/'.($ext === 'png' ? 'png' : 'jpeg').';base64,'.base64_encode($raw);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
