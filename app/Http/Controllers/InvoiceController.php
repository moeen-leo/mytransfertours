<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable invoice for a booking: /invoices/{booking}. Staff can open any;
 * customers only their own. "Print / Save as PDF" uses the browser.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, Booking $booking): View
    {
        $staff = $request->user('admin');
        $customer = $request->user('web');

        abort_unless(($staff && $staff->isStaff()) || ($customer && $booking->user_id === $customer->id), 403);
        abort_unless(filled($booking->invoice_number), 404);

        $booking->load(['items.transfer', 'items.tour', 'items.tickets', 'items.extras']);

        return view('invoices.show', [
            'booking' => $booking,
            'company' => [
                'name' => Setting::get('company_name', 'MyTransferTours'),
                'address' => Setting::get('company_address', ''),
                'registration' => Setting::get('company_registration', ''),
                'email' => Setting::get('company_email', config('mail.from.address')),
                'phone' => Setting::get('company_phone', ''),
                'bank' => Setting::get('company_bank_details', ''),
            ],
        ]);
    }
}
