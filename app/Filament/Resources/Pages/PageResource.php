<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** About us, Contact, FAQ, Terms, Cancellation and Privacy pages. Admins only. */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 95;

    protected static ?string $navigationLabel = 'Website pages';

    protected static ?string $modelLabel = 'page';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;   // fixed set of pages with fixed addresses
    }

    public static function canDelete($record): bool
    {
        return false;   // hide a page with "Published" instead
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(120),
                TextInput::make('slug')->label('Address')->disabled()->dehydrated(false)
                    ->prefix(fn () => rtrim(url('/'), '/').'/'),
                TextInput::make('meta_description')->label('Search engine description')->maxLength(300)
                    ->helperText('One or two sentences shown in Google results.')->columnSpanFull(),
                Toggle::make('is_published')->label('Published (shown on the website and in the footer)'),
            ]),
            Section::make('Text')->schema([
                MarkdownEditor::make('body')->hiddenLabel()->required()
                    ->disableToolbarButtons(['attachFiles', 'table', 'codeBlock'])
                    ->helperText('These are filled in automatically from Setup > Company & invoices: {company_name}, {company_email}, {company_phone}, {company_address}, {company_registration}, {company_details} (all of them, one per line) and {site_url}. This text is a general template, not legal advice: please have it checked for your business.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->weight('semibold')->description(fn (Page $r) => '/'.$r->slug),
                TextColumn::make('updated_at')->label('Last edited')->since(),
                ToggleColumn::make('is_published')->label('Published'),
            ])
            ->recordActions([
                Action::make('view')->label('View')->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Page $r) => $r->url(), true),
                EditAction::make(),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
