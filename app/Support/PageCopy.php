<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\HtmlString;

/**
 * Headings and SEO text for the main site pages, edited in the admin under
 * Setup → Page headings and stored in the settings table (key "page_copy").
 * A label, heading or text left empty is hidden on the page; an empty SEO
 * title or description uses the site's default one.
 */
class PageCopy
{
    public const SETTING_KEY = 'page_copy';

    public const FIELDS = ['label', 'heading', 'subheading', 'seo_title', 'seo_description'];

    /** Admin names for each page. */
    public const PAGES = [
        'home' => 'Home page',
        'transfers' => 'Transfers page',
        'tours' => 'Tours page',
        'tickets' => 'Attraction tickets page',
    ];

    public static function defaults(): array
    {
        return [
            'home' => [
                'label' => 'Malaysia first · more countries soon',
                'heading' => "Land. Explore.\nSee it all.\n*One booking.*",
                'subheading' => 'Airport transfers, day tours and attraction tickets in one trip. One payment, instant confirmation, and your driver already has the details.',
                'seo_title' => '',
                'seo_description' => '',
            ],
            'transfers' => [
                'label' => '',
                'heading' => 'Transfers',
                'subheading' => "Private car, MPV or van. The price is for the whole vehicle, not per person. Your driver tracks your flight and waits if it's late.",
                'seo_title' => 'Airport & city transfers in Malaysia',
                'seo_description' => 'Private airport and city transfers in Malaysia with a fixed price per car. KLIA, klia2 and hotels: meet and greet, flight tracking and free waiting time.',
            ],
            'tours' => [
                'label' => '',
                'heading' => 'Tours',
                'subheading' => 'Join a shared group (seat-in-coach) or go private in your own vehicle.',
                'seo_title' => 'Tours in Malaysia',
                'seo_description' => 'Day tours in and around Kuala Lumpur: join a shared tour or book a private car with driver. Hotel pick-up, fixed prices and instant confirmation.',
            ],
            'tickets' => [
                'label' => '',
                'heading' => 'Attraction tickets',
                'subheading' => 'Pick a date and how many tickets. Your e-ticket arrives by email.',
                'seo_title' => 'Attraction tickets in Malaysia',
                'seo_description' => "Skip the queue with attraction tickets for Malaysia's top sights. Choose your date, pay once and get your tickets by email.",
            ],
        ];
    }

    /**
     * The wording for one page. Until a page is saved in the admin it shows the
     * default wording; after that it shows exactly what was saved, so a field
     * left empty shows nothing.
     */
    public static function for(string $page): array
    {
        $saved = Setting::get(self::SETTING_KEY, []);
        $defaults = self::defaults()[$page] ?? array_fill_keys(self::FIELDS, '');

        if (! is_array($saved) || ! is_array($saved[$page] ?? null)) {
            return $defaults;
        }

        return collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => trim((string) ($saved[$page][$f] ?? ''))])->all();
    }

    public static function get(string $page, string $field): string
    {
        return self::for($page)[$field] ?? '';
    }

    /**
     * A heading as safe HTML: each new line becomes a line break, and text in
     * *stars* gets the highlight colour.
     */
    public static function heading(string $page): HtmlString
    {
        $html = e(self::get($page, 'heading'));
        $html = preg_replace('/\*(.+?)\*/s', '<span>$1</span>', $html);

        return new HtmlString(str_replace(["\r\n", "\r", "\n"], '<br>', $html));
    }

    /** Layout arguments (title, description), leaving out empty ones so the site defaults apply. */
    public static function seo(string $page): array
    {
        $copy = self::for($page);

        return array_filter([
            'title' => $copy['seo_title'],
            'description' => $copy['seo_description'],
        ], fn ($v) => $v !== '');
    }
}
