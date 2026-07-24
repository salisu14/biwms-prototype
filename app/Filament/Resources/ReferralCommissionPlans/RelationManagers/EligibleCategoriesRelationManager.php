<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\RelationManagers;

use App\Models\ReferralCommissionPlanCategory;
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

class EligibleCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'eligibleCategories';

    protected static ?string $title = 'Eligible Categories';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->relationship('category', 'category_name')
                ->searchable()
                ->required(),
            Toggle::make('is_included')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.category_code')->searchable(),
                TextColumn::make('category.category_name')->searchable(),
                IconColumn::make('is_included')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn (ReferralCommissionPlanCategory $record) => $this->auditEligibility('referral_commission_plan_category_added', $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (ReferralCommissionPlanCategory $record) => $this->auditEligibility('referral_commission_plan_category_changed', $record)),
                DeleteAction::make()
                    ->before(fn (ReferralCommissionPlanCategory $record) => $this->auditEligibility('referral_commission_plan_category_removed', $record)),
            ]);
    }

    private function auditEligibility(string $action, ReferralCommissionPlanCategory $eligibility): void
    {
        app(AuditTrailService::class)->recordSetupChange(
            auditable: $eligibility->plan,
            action: $action,
            userId: auth()->id(),
            metadata: [
                'eligibility_id' => $eligibility->id,
                'category_id' => $eligibility->category_id,
                'category_code' => $eligibility->category?->category_code,
                'is_included' => $eligibility->is_included,
            ],
        );
    }
}
