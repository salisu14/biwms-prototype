<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferrerCommissionPlanAssignments\Tables;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionPlanStatus;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPlanAssignmentService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferrerCommissionPlanAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer.name')->searchable()->sortable(),
                TextColumn::make('plan.code')->label('Plan')->searchable()->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color()),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->placeholder('Open'),
                IconColumn::make('is_primary')->boolean(),
                TextColumn::make('assignedBy.name')->label('Assigned By')->placeholder('—'),
                TextColumn::make('assigned_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReferralCommissionAssignmentStatus::class),
                SelectFilter::make('referrer_id')->relationship('referrer', 'name')->searchable(),
                SelectFilter::make('referral_commission_plan_id')->relationship('plan', 'name')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('change_plan')
                        ->label('Change Plan')
                        ->visible(fn (ReferrerCommissionPlanAssignment $record): bool => $record->status === ReferralCommissionAssignmentStatus::ACTIVE && (auth()->user()?->can('change', $record) ?? false))
                        ->form(self::changeForm())
                        ->action(fn (ReferrerCommissionPlanAssignment $record, array $data) => app(ReferrerCommissionPlanAssignmentService::class)->change(
                            $record->referrer,
                            ReferralCommissionPlan::query()->findOrFail($data['referral_commission_plan_id']),
                            $data,
                            auth()->id(),
                        ))
                ),
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

    private static function changeForm(): array
    {
        return [
            Select::make('referral_commission_plan_id')
                ->label('New Commission Plan')
                ->options(fn (): array => ReferralCommissionPlan::query()->where('status', ReferralCommissionPlanStatus::ACTIVE)->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('effective_from')->default(today())->required(),
            Textarea::make('reason')->required(),
        ];
    }
}
