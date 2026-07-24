<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferralCommissionPlans\Tables;

use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Models\ReferralCommissionPlan;
use App\Services\Sales\ReferralCommissions\ReferralCommissionPlanService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralCommissionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color()),
                TextColumn::make('commission_basis')->badge(),
                TextColumn::make('commission_method')->badge(),
                TextColumn::make('commission_scope')->toggleable(),
                TextColumn::make('percentage_rate')->label('Rate %')->placeholder('—'),
                TextColumn::make('fixed_amount')->money(fn ($record): string => $record->currency?->code ?? 'NGN')->placeholder('—'),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->placeholder('Open'),
                IconColumn::make('is_default')->boolean()->label('Default'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReferralCommissionPlanStatus::class),
                SelectFilter::make('commission_basis')->options(ReferralCommissionBasis::class),
                SelectFilter::make('commission_method')->options(ReferralCommissionMethod::class),
                SelectFilter::make('commission_scope')->options(ReferralCommissionScope::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->visible(fn (ReferralCommissionPlan $record): bool => $record->status !== ReferralCommissionPlanStatus::ACTIVE && (auth()->user()?->can('activate', $record) ?? false))
                    ->action(fn (ReferralCommissionPlan $record) => app(ReferralCommissionPlanService::class)->activate($record, auth()->id())),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('inactivate')
                        ->visible(fn (ReferralCommissionPlan $record): bool => $record->status === ReferralCommissionPlanStatus::ACTIVE && (auth()->user()?->can('inactivate', $record) ?? false))
                        ->action(fn (ReferralCommissionPlan $record) => app(ReferralCommissionPlanService::class)->inactivate($record, auth()->id()))
                ),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('archive')
                        ->visible(fn (ReferralCommissionPlan $record): bool => $record->status !== ReferralCommissionPlanStatus::ACTIVE && (auth()->user()?->can('archive', $record) ?? false))
                        ->action(fn (ReferralCommissionPlan $record) => app(ReferralCommissionPlanService::class)->archive($record, auth()->id()))
                ),
            ]);
    }
}
