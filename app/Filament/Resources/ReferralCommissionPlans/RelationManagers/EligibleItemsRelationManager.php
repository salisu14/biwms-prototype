<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\RelationManagers;

use App\Models\ReferralCommissionPlanItem;
use App\Services\AuditTrailService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EligibleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'eligibleItems';

    protected static ?string $title = 'Eligible Items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_id')
                ->relationship('item', 'description')
                ->searchable()
                ->required(),
            Toggle::make('is_included')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')->searchable(),
                TextColumn::make('item.description')->searchable(),
                IconColumn::make('is_included')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn (ReferralCommissionPlanItem $record) => $this->auditEligibility('referral_commission_plan_item_added', $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (ReferralCommissionPlanItem $record) => $this->auditEligibility('referral_commission_plan_item_changed', $record)),
                DeleteAction::make()
                    ->before(fn (ReferralCommissionPlanItem $record) => $this->auditEligibility('referral_commission_plan_item_removed', $record)),
            ]);
    }

    private function auditEligibility(string $action, ReferralCommissionPlanItem $eligibility): void
    {
        app(AuditTrailService::class)->recordSetupChange(
            auditable: $eligibility->plan,
            action: $action,
            userId: auth()->id(),
            metadata: [
                'eligibility_id' => $eligibility->id,
                'item_id' => $eligibility->item_id,
                'item_code' => $eligibility->item?->item_code,
                'is_included' => $eligibility->is_included,
            ],
        );
    }
}
