<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Your company details, printed on every invoice. Stored in the settings table. */
class CompanySettings extends Page
{
    protected string $view = 'filament.pages.company-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Company & invoices';

    protected static ?string $title = 'Company & invoices';

    private const KEYS = ['company_name', 'company_address', 'company_registration', 'company_email', 'company_phone', 'company_bank_details'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \Filament\Facades\Filament::auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Shown at the top of every invoice')->columns(2)->schema([
                    TextInput::make('company_name')->label('Company name')->required()->placeholder('MyTransferTours Sdn Bhd'),
                    TextInput::make('company_registration')->label('Registration no.')->placeholder('202601012345 (1234567-X)'),
                    Textarea::make('company_address')->label('Address')->rows(3)->columnSpanFull(),
                    TextInput::make('company_email')->label('Email')->email(),
                    TextInput::make('company_phone')->label('Phone / WhatsApp')->tel(),
                ]),
                Section::make('Bank details for credit customers')
                    ->description('Printed on unpaid on-account invoices so customers know where to pay.')
                    ->schema([
                        Textarea::make('company_bank_details')->hiddenLabel()->rows(4)
                            ->placeholder("Bank: Maybank\nAccount name: MyTransferTours Sdn Bhd\nAccount no.: 5123 4567 8901\nSWIFT: MBBEMYKL"),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                Setting::put($key, $value);
            }
        }

        Notification::make()->title('Saved')->success()->send();
    }
}
