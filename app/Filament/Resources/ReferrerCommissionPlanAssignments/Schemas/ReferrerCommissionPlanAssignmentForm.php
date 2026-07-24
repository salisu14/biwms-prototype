<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferrerCommissionPlanAssignments\Schemas;

use App\Enums\ReferralCommissionPlanStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferrerCommissionPlanAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Assignment')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('referrer_id')
                        ->relationship('referrer', 'name', fn ($query) => $query->where('is_active', true)->where('commission_eligible', true))
                        ->searchable()
                        ->required(),
                    Select::make('referral_commission_plan_id')
                        ->label('Commission Plan')
                        ->relationship('plan', 'name', fn ($query) => $query->where('status', ReferralCommissionPlanStatus::ACTIVE))
                        ->searchable()
                        ->required(),
                    DatePicker::make('effective_from')->required()->default(today()),
                    Toggle::make('is_primary')->default(true),
                    Textarea::make('assignment_reason')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }
}
