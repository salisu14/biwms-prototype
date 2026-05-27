<?php

namespace App\Filament\Resources\CompanyInformation\Schemas;

use App\Enums\CountryCode;
use App\Enums\CurrencyCode;
use App\Enums\FiscalMonth;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInformationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Identity')
                    ->schema([
                        TextEntry::make('company_name'),
                        TextEntry::make('trading_name')->placeholder('-'),
                        TextEntry::make('registration_no')->placeholder('-'),
                        TextEntry::make('tax_registration_no')->placeholder('-'),
                        TextEntry::make('tax_office')->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Address & Contact')
                    ->schema([
                        TextEntry::make('address_line_1')->placeholder('-'),
                        TextEntry::make('address_line_2')->placeholder('-'),
                        TextEntry::make('city')->placeholder('-'),
                        TextEntry::make('state_province')->placeholder('-'),
                        TextEntry::make('postal_code')->placeholder('-'),
                        TextEntry::make('country_code')
                            ->formatStateUsing(fn (?string $state): string => CountryCode::tryFrom((string) $state)?->label() ?? (string) $state),
                        TextEntry::make('phone_no')->placeholder('-'),
                        TextEntry::make('mobile_no')->placeholder('-'),
                        TextEntry::make('email')
                            ->label('Email address')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => $state ? "mailto:{$state}" : null),
                        TextEntry::make('website')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => $state ? (str_starts_with($state, 'http') ? $state : "https://{$state}") : null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Section::make('Primary Contact')
                    ->schema([
                        TextEntry::make('contact_person_name')->placeholder('-'),
                        TextEntry::make('contact_person_title')->placeholder('-'),
                        TextEntry::make('contact_person_phone')->placeholder('-'),
                        TextEntry::make('contact_person_email')
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => $state ? "mailto:{$state}" : null),
                    ])
                    ->columns(2),

                Section::make('Branding & Banking')
                    ->schema([
                        ImageEntry::make('logo_path')->label('Logo')->disk('public')->height(64),
                        ImageEntry::make('favicon_path')->label('Favicon')->disk('public')->height(24),
                        TextEntry::make('bank_name')->placeholder('-'),
                        TextEntry::make('bank_account_no')->placeholder('-'),
                        TextEntry::make('bank_branch')->placeholder('-'),
                        TextEntry::make('swift_code')->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Fiscal & Document Settings')
                    ->schema([
                        TextEntry::make('fiscal_year_start_month')
                            ->label('Fiscal Year Start Month')
                            ->formatStateUsing(fn (?string $state): string => FiscalMonth::tryFrom((string) $state)?->label() ?? (string) $state),
                        TextEntry::make('base_currency_code')
                            ->formatStateUsing(fn (?string $state): string => CurrencyCode::tryFrom((string) $state)?->value ?? (string) $state),
                        TextEntry::make('reporting_currency_code')
                            ->placeholder('-')
                            ->formatStateUsing(fn (?string $state): string => CurrencyCode::tryFrom((string) $state)?->value ?? ((string) $state ?: '-')),
                        TextEntry::make('terms_conditions')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('invoice_footer')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                    ])
                    ->columns(2),
            ])
            ->columns(1);
    }
}
