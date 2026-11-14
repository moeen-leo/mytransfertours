<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\BookingMailer;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = Page::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('site.page', [
            'page' => $page,
            'company' => app(BookingMailer::class)->company(),
        ]);
    }
}
