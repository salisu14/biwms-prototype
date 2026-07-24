<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Schemas;

use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Enums\ReferralCommissionTierBasis;
use App\Enums\ReferralFixedAmountApplication;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferralCommissionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General Information')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    TextInput::make('code')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Generated on save'),
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('business_id')->relationship('business', 'name')->searchable(),
                    Select::make('status')->options(ReferralCommissionPlanStatus::class)->default(ReferralCommissionPlanStatus::DRAFT)->disabled()->dehydrated(false),
                    TextInput::make('priority')->numeric()->default(100),
                    Toggle::make('is_default')->label('Default Plan')->default(false),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                ]),
            Section::make('Commission Basis and Method')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Select::make('commission_basis')->options(ReferralCommissionBasis::class)->default(ReferralCommissionBasis::POSTED_SALES)->required(),
                    Select::make('commission_method')->options(ReferralCommissionMethod::class)->default(ReferralCommissionMethod::PERCENTAGE)->required()->live(),
                    Select::make('commission_scope')->options(ReferralCommissionScope::class)->default(ReferralCommissionScope::ALL_ELIGIBLE_SALES)->required(),
                    Select::make('tier_basis')->options(ReferralCommissionTierBasis::class)->visible(fn ($get): bool => str_contains(self::enumValue($get('commission_method')), 'TIERED')),
                    Select::make('fixed_amount_application')->options(ReferralFixedAmountApplication::class)->visible(fn ($get): bool => str_contains(self::enumValue($get('commission_method')), 'FIXED_AMOUNT')),
                    Select::make('currency_id')->relationship('currency', 'code')->searchable(),
                    TextInput::make('percentage_rate')->numeric()->minValue(0)->maxValue(100)->visible(fn ($get): bool => self::enumValue($get('commission_method')) === ReferralCommissionMethod::PERCENTAGE->value),
                    TextInput::make('fixed_amount')->numeric()->minValue(0)->visible(fn ($get): bool => self::enumValue($get('commission_method')) === ReferralCommissionMethod::FIXED_AMOUNT->value),
                    TextInput::make('minimum_eligible_amount')->numeric()->minValue(0),
                    TextInput::make('maximum_commission_amount')->numeric()->minValue(0),
                ]),
            Section::make('Effective Dates')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    DatePicker::make('effective_from')->required()->default(today()),
                    DatePicker::make('effective_to'),
                ]),
            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }

    private static function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
