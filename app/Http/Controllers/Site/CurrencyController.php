<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Money::set((string) $request->input('currency'));

        return back();
    }
}
