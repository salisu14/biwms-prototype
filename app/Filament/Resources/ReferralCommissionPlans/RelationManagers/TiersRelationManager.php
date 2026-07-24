<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\RelationManagers;

use App\Models\ReferralCommissionPlanTier;
use App\Services\AuditTrailService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Commission Tiers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sequence')->numeric()->required(),
            TextInput::make('minimum_threshold')->numeric()->minValue(0)->required(),
            TextInput::make('maximum_threshold')->numeric()->minValue(0),
            TextInput::make('percentage_rate')->numeric()->minValue(0)->maxValue(100),
            TextInput::make('fixed_amount')->numeric()->minValue(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')->sortable(),
                TextColumn::make('minimum_threshold'),
                TextColumn::make('maximum_threshold')->placeholder('Open'),
                TextColumn::make('percentage_rate')->placeholder('—'),
                TextColumn::make('fixed_amount')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn (ReferralCommissionPlanTier $record) => $this->auditTier('referral_commission_plan_tier_added', $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (ReferralCommissionPlanTier $record) => $this->auditTier('referral_commission_plan_tier_changed', $record)),
                DeleteAction::make()
                    ->before(fn (ReferralCommissionPlanTier $record) => $this->auditTier('referral_commission_plan_tier_removed', $record)),
            ]);
    }

    private function auditTier(string $action, ReferralCommissionPlanTier $tier): void
    {
        app(AuditTrailService::class)->recordSetupChange(
            auditable: $tier->plan,
            action: $action,
            userId: auth()->id(),
            metadata: [
                'tier_id' => $tier->id,
                'sequence' => $tier->sequence,
                'minimum_threshold' => $tier->minimum_threshold,
                'maximum_threshold' => $tier->maximum_threshold,
                'percentage_rate' => $tier->percentage_rate,
                'fixed_amount' => $tier->fixed_amount,
            ],
        );
    }
}
