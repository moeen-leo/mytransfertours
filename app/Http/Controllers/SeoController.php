<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\Tour;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml and robots.txt, built from the live catalogue. */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap', now()->addHour(), function () {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0'],
                ['loc' => route('transfers.search'), 'priority' => '0.9'],
                ['loc' => route('tours.index'), 'priority' => '0.9'],
                ['loc' => route('tickets.index'), 'priority' => '0.9'],
            ];

            \App\Models\Page::query()->where('is_published', true)->orderBy('sort')->get(['slug', 'updated_at'])
                ->each(function (\App\Models\Page $p) use (&$urls) {
                    $urls[] = ['loc' => route('page', $p->slug), 'lastmod' => $p->updated_at?->toAtomString(), 'priority' => '0.4'];
                });

            Tour::query()->where('is_active', true)->orderBy('sort')->get(['slug', 'updated_at'])
                ->each(function (Tour $t) use (&$urls) {
                    $urls[] = ['loc' => route('tours.show', $t), 'lastmod' => $t->updated_at?->toAtomString(), 'priority' => '0.8'];
                });
            Attraction::query()->where('is_active', true)->orderBy('sort')->get(['slug', 'updated_at'])
                ->each(function (Attraction $a) use (&$urls) {
                    $urls[] = ['loc' => route('tickets.show', $a), 'lastmod' => $a->updated_at?->toAtomString(), 'priority' => '0.8'];
                });

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $u) {
                $out .= '  <url><loc>'.e($u['loc']).'</loc>'
                    .(! empty($u['lastmod']) ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '')
                    .'<priority>'.$u['priority'].'</priority></url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots(): Response
    {
        // Only the live site is indexed; dev/staging tell every crawler to stay out.
        $body = app()->isProduction()
            ? implode("\n", [
                'User-agent: *',
                'Disallow: /checkout',
                'Disallow: /trip',
                'Disallow: /booking/',
                'Disallow: /my-booking/',
                'Disallow: /find-booking',
                'Disallow: /account/',
                'Disallow: /invoices/',
                'Disallow: /supplier/',
                'Disallow: /auth/',
                'Disallow: /login',
                'Disallow: /register',
                '',
                'Sitemap: '.route('sitemap'),
            ])
            : "User-agent: *\nDisallow: /";

        return response($body."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
