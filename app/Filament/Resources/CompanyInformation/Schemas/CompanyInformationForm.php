<?php

namespace App\Filament\Resources\CompanyInformation\Schemas;

use App\Enums\CountryCode;
use App\Enums\CurrencyCode;
use App\Enums\FiscalMonth;
use App\Models\Business;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInformationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // LEFT COLUMN (2/3 width)
                Group::make()->schema([

                    Section::make('Company Identity')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Placeholder::make('active_business_indicator')
                                ->label('Active Business Context')
                                ->content(function ($record): string {
                                    if (! $record) {
                                        return 'Will become active when selected in the business switcher.';
                                    }

                                    $activeBusinessId = (int) session('active_business_id', 0);
                                    $recordBusinessId = (int) ($record->business_id ?? 0);

                                    return $activeBusinessId === $recordBusinessId
                                        ? 'This profile is currently active in your session.'
                                        : 'This profile is not currently active in your session.';
                                }),
                            Select::make('business_id')
                                ->label('Business / Trade Name')
                                ->options(
                                    Business::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Each business keeps its own company profile, logo, and invoice footer.'),
                            Grid::make(2)->schema([
                                TextInput::make('company_name')
                                    ->required()
                                    ->placeholder('Petrichor Industries Limited'),
                                TextInput::make('trading_name')
                                    ->placeholder('Petrichor Foods'),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('registration_no')
                                    ->placeholder('RC123456'),
                                TextInput::make('tax_registration_no')
                                    ->placeholder('12345678-0001'),
                                TextInput::make('tax_office')
                                    ->placeholder('FIRS Lagos'),
                            ]),
                        ]),

                    Section::make('Address')
                        ->icon('heroicon-o-map-pin')
                        ->collapsible()
                        ->schema([
                            TextInput::make('address_line_1'),
                            TextInput::make('address_line_2'),
                            Grid::make(3)->schema([
                                TextInput::make('city')->required(),
                                TextInput::make('state_province')->required(),
                                TextInput::make('postal_code'),
                            ]),
                            Select::make('country_code')
                                ->options(CountryCode::toArray())
                                ->searchable()
                                ->default('NGA'),
                        ]),

                    Section::make('Contact Information')
                        ->icon('heroicon-o-phone')
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('phone_no')
                                    ->tel()->placeholder('+234 123 456 7890'),
                                TextInput::make('mobile_no')
                                    ->tel()->placeholder('+234 800 123 4567'),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('email')->email(),
                                TextInput::make('website')
                                    ->prefix('https://')->placeholder('www.company.com'),
                            ]),
                        ]),

                    Section::make('Primary Contact Person')
                        ->icon('heroicon-o-user')
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('contact_person_name')
                                    ->placeholder('John Doe'),
                                TextInput::make('contact_person_title')
                                    ->placeholder('Managing Director'),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('contact_person_phone')->tel(),
                                TextInput::make('contact_person_email')->email(),
                            ]),
                        ]),

                ])->columnSpan(['lg' => 2]),

                // RIGHT COLUMN (1/3 width)
                Group::make()->schema([

                    Section::make('Logo & Branding')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('logo_path')
                                ->label('Logo')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios(['3:2'])
                                ->maxSize(2048)
                                ->directory('company/logos')
                                ->disk('public')
                                ->visibility('public') // Explicitly set public visibility
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/svg+xml']),

                            FileUpload::make('favicon_path')
                                ->label('Favicon')
                                ->image()
                                ->maxSize(512)
                                ->directory('company/favicons')
                                ->disk('public')
                                ->visibility('public'),
                        ]),

                    Section::make('Banking Details')
                        ->icon('heroicon-o-credit-card')
                        ->collapsible()
                        ->schema([
                            TextInput::make('bank_name'),
                            TextInput::make('bank_account_no'),
                            TextInput::make('bank_branch'),
                            TextInput::make('swift_code')->placeholder('FBNINGLA'),
                        ]),

                    Section::make('Fiscal Settings')
                        ->icon('heroicon-o-calendar')
                        ->collapsible()
                        ->schema([
                            Select::make('fiscal_year_start_month')
                                ->options(FiscalMonth::toArray())
                                ->default('01'),
                            Select::make('base_currency_code')
                                ->options(CurrencyCode::toArray())
                                ->searchable()
                                ->default('NGN'),
                            Select::make('reporting_currency_code')
                                ->options(CurrencyCode::toArray())
                                ->searchable()
                                ->placeholder('Optional ACY'),
                        ]),

                ])->columnSpan(['lg' => 1]),

                // FULL WIDTH
                Section::make('Document Settings')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Textarea::make('terms_conditions')
                            ->rows(4)
                            ->helperText('Appears on invoices by default'),
                        Textarea::make('invoice_footer')
                            ->rows(2)
                            ->helperText('Footer text on all invoices'),
                    ])
                    ->columnSpanFull(),

            ])->columns(3);
    }
}
