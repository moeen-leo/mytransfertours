<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function bookings(Request $request): View
    {
        $user = $request->user();

        return view('site.account.bookings', [
            'user' => $user,
            'bookings' => $user->bookings()->with('items')->latest()->paginate(20),
            'outstanding' => $user->credit_enabled ? $user->creditOutstanding() : null,
            'available' => $user->credit_enabled ? $user->creditAvailable() : null,
        ]);
    }
}
