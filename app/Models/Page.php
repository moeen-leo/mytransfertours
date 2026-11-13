<?php

namespace App\Models;

use App\Services\BookingMailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** An information page such as About us or the Privacy policy. */
class Page extends Model
{
    /** Fixed addresses; the routes only answer for these. */
    public const SLUGS = ['about-us', 'contact-us', 'faq', 'terms', 'cancellation-policy', 'privacy-policy'];

    protected $guarded = ['id'];

    protected $casts = ['is_published' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('pages:footer');
            Cache::forget('seo:sitemap');
        });
    }

    /** Published pages for the footer, cached. */
    public static function footerLinks(): array
    {
        try {
            return Cache::rememberForever('pages:footer', fn () => static::query()
                ->where('is_published', true)->orderBy('sort')->get(['slug', 'title'])
                ->map(fn (Page $p) => ['slug' => $p->slug, 'title' => $p->title])->all());
        } catch (\Throwable) {
            return [];   // e.g. before the migration has run
        }
    }

    /** Fills in {company_name} etc. from Company & invoices settings. */
    public static function placeholders(): array
    {
        $company = app(BookingMailer::class)->company();
        $address = trim((string) Setting::get('company_address', ''));
        $registration = trim((string) Setting::get('company_registration', ''));
        $hardBreak = fn (string $s) => preg_replace('/\R/', "  \n", $s);

        $details = array_filter([
            '**'.$company['name'].'**'.($registration !== '' ? ' ('.$registration.')' : ''),
            $address !== '' ? $hardBreak($address) : null,
            $company['email'] ? 'Email: ['.$company['email'].'](mailto:'.$company['email'].')' : null,
            $company['phone'] ? 'Phone / WhatsApp: '.$company['phone'] : null,
        ]);

        return [
            '{company_name}' => $company['name'],
            '{company_email}' => $company['email'] ? '['.$company['email'].'](mailto:'.$company['email'].')' : 'our support team',
            '{company_phone}' => $company['phone'] ?: '',
            '{company_address}' => $hardBreak($address),
            '{company_registration}' => $registration,
            '{site_url}' => preg_replace('#^https?://#', '', rtrim(url('/'), '/')),
            '{company_details}' => implode("  \n", $details),
        ];
    }

    public function html(): HtmlString
    {
        $markdown = strtr($this->body, static::placeholders());

        return new HtmlString(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'renderer' => ['soft_break' => "<br>\n"]]));
    }

    public function url(): string
    {
        return route('page', $this->slug);
    }
}
