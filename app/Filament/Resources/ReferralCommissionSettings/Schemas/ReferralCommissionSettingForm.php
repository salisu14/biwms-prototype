<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionSettings\Schemas;

use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionPlanStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferralCommissionSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Select::make('business_id')
                        ->relationship('business', 'name')
                        ->searchable()
                        ->required()
                        ->disabled(fn ($record): bool => $record !== null)
                        ->dehydrated(),
                    Toggle::make('is_enabled')->label('Enable Referral Commissions')->default(false),
                    Select::make('default_commission_basis')->options(ReferralCommissionBasis::class)->default(ReferralCommissionBasis::POSTED_SALES)->required(),
                    Toggle::make('require_plan_assignment')->label('Require Explicit Plan Assignment')->default(true),
                    Select::make('default_plan_id')
                        ->label('Default Commission Plan')
                        ->relationship('defaultPlan', 'name', fn ($query) => $query->where('status', ReferralCommissionPlanStatus::ACTIVE))
                        ->searchable(),
                    Select::make('commission_currency_id')
                        ->label('Commission Currency')
                        ->relationship('commissionCurrency', 'code')
                        ->searchable(),
                ]),
            Section::make('Commission Base')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Toggle::make('include_tax_in_commission_base')->label('Include Tax')->default(false),
                    Toggle::make('include_shipping_in_commission_base')->label('Include Shipping')->default(false),
                    Toggle::make('deduct_line_discounts')->label('Deduct Line Discounts')->default(true),
                    Toggle::make('deduct_invoice_discounts')->label('Deduct Invoice Discounts')->default(true),
                    Toggle::make('allow_commission_on_zero_value_lines')->label('Allow Zero-Value Lines')->default(false),
                    Toggle::make('allow_commission_on_free_items')->label('Allow Free Items')->default(false),
                    Toggle::make('allow_commission_for_inactive_referrer')->label('Allow Inactive Referrers')->default(false),
                    TextInput::make('minimum_eligible_sale_amount')->numeric()->minValue(0),
                    TextInput::make('commission_decimal_places')->numeric()->minValue(0)->maxValue(6)->default(4)->required(),
                    TextInput::make('rounding_mode')->maxLength(100),
                ]),
            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }
}
