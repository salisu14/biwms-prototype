<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\RelationManagers;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionPlanStatus;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPlanAssignmentService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommissionPlanHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionPlanAssignments';

    protected static ?string $title = 'Commission Plan History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan.code')->label('Plan')->searchable(),
                TextColumn::make('plan.name')->label('Plan Name')->searchable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color()),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date()->placeholder('Open'),
                TextColumn::make('assignment_reason')->limit(30)->placeholder('—'),
                TextColumn::make('end_reason')->limit(30)->placeholder('—'),
                TextColumn::make('assignedBy.name')->label('Assigned By')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('assign_plan')
                    ->label('Assign Plan')
                    ->visible(fn (): bool => auth()->user()?->can('assign', ReferrerCommissionPlanAssignment::class) === true)
                    ->form($this->planActionForm())
                    ->action(function (array $data): void {
                        app(ReferrerCommissionPlanAssignmentService::class)->assign(
                            $this->getOwnerRecord(),
                            ReferralCommissionPlan::query()->findOrFail($data['referral_commission_plan_id']),
                            $data,
                            auth()->id(),
                        );
                    }),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('change_plan')
                        ->label('Change Plan')
                        ->visible(fn (): bool => auth()->user()?->can('change', ReferrerCommissionPlanAssignment::class) === true)
                        ->form([
                            ...$this->planActionForm(),
                            Textarea::make('reason')->required(),
                        ])
                        ->action(function (array $data): void {
                            app(ReferrerCommissionPlanAssignmentService::class)->change(
                                $this->getOwnerRecord(),
                                ReferralCommissionPlan::query()->findOrFail($data['referral_commission_plan_id']),
                                $data,
                                auth()->id(),
                            );
                        })
                ),
            ])
            ->recordActions([
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('end')
                        ->visible(fn (ReferrerCommissionPlanAssignment $record): bool => $record->status === ReferralCommissionAssignmentStatus::ACTIVE && (auth()->user()?->can('end', $record) ?? false))
                        ->form([
                            DatePicker::make('effective_to')->default(today()),
                            Textarea::make('reason')->required(),
                        ])
                        ->action(fn (ReferrerCommissionPlanAssignment $record, array $data) => app(ReferrerCommissionPlanAssignmentService::class)->end($record, $data['reason'], $data['effective_to'] ? Carbon::parse($data['effective_to']) : null, auth()->id()))
                ),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('cancel')
                        ->visible(fn (ReferrerCommissionPlanAssignment $record): bool => $record->status !== ReferralCommissionAssignmentStatus::CANCELLED && (auth()->user()?->can('cancel', $record) ?? false))
                        ->form([Textarea::make('reason')->required()])
                        ->action(fn (ReferrerCommissionPlanAssignment $record, array $data) => app(ReferrerCommissionPlanAssignmentService::class)->cancel($record, $data['reason'], auth()->id()))
                ),
            ]);
    }

    private function planActionForm(): array
    {
        return [
            Select::make('referral_commission_plan_id')
                ->label('Commission Plan')
                ->options(fn (): array => ReferralCommissionPlan::query()->where('status', ReferralCommissionPlanStatus::ACTIVE)->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('effective_from')->default(today())->required(),
            Textarea::make('assignment_reason')->rows(3),
        ];
    }
}
