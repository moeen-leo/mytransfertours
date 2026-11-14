<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\PageCopy;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Headings and SEO text for the home, transfers, tours and tickets pages. */
class PageHeadings extends Page
{
    protected string $view = 'filament.pages.company-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = 'Page headings';

    protected static ?string $title = 'Page headings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \Filament\Facades\Filament::auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(collect(PageCopy::PAGES)->keys()->mapWithKeys(fn ($p) => [$p => PageCopy::for($p)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(collect(PageCopy::PAGES)->map(fn ($name, $page) => Section::make($name)
                ->description('Leave the label, heading or text empty to hide it. Leave the Google fields empty to use the site default.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    TextInput::make("{$page}.label")->label('Small label above the heading')->maxLength(80),
                    Textarea::make("{$page}.heading")->label('Heading')->rows(3)->maxLength(200)
                        ->helperText('A new line starts a new line on the page. Put text in *stars* to show it in the highlight colour.'),
                    Textarea::make("{$page}.subheading")->label('Text under the heading')->rows(3)->maxLength(400)->columnSpanFull(),
                    TextInput::make("{$page}.seo_title")->label('Browser tab / Google title')->maxLength(70)
                        ->helperText('" · MyTransferTours" is added at the end.'),
                    Textarea::make("{$page}.seo_description")->label('Google description')->rows(2)->maxLength(160),
                ]))->values()->all());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $saved = [];

        foreach (array_keys(PageCopy::PAGES) as $page) {
            foreach (PageCopy::FIELDS as $field) {
                $saved[$page][$field] = trim((string) ($state[$page][$field] ?? ''));
            }
        }

        Setting::put(PageCopy::SETTING_KEY, $saved);
        $this->mount();

        Notification::make()->title('Saved')->body('The pages show the new wording now.')->success()->send();
    }
}
