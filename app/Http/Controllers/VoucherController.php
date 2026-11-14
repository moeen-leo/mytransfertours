<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\VoucherPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Booking voucher PDF: /vouchers/{booking}. Staff (admin login) can download any;
 * customers (website login) only their own. Guests use /my-booking/{token}/voucher.
 */
class VoucherController extends Controller
{
    public function show(Request $request, Booking $booking, VoucherPdf $vouchers): Response
    {
        $staff = $request->user('admin');
        $customer = $request->user('web');

        abort_unless(($staff && $staff->isStaff()) || ($customer && $booking->user_id === $customer->id), 403);
        abort_unless(VoucherPdf::available($booking), 404);

        return self::download($booking, $vouchers);
    }

    public static function download(Booking $booking, VoucherPdf $vouchers): Response
    {
        return response($vouchers->render($booking), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$vouchers->filename($booking).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
