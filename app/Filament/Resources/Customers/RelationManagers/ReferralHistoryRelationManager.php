<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\CustomerReferralStatus;
use App\Models\CustomerReferral;
use App\Models\Referrer;
use App\Services\Sales\CustomerReferralService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferralHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static ?string $title = 'Referral History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer.name')->label('Referrer')->searchable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state?->color()),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date()->placeholder('Open'),
                TextColumn::make('end_reason')->label('Reason')->placeholder('—')->limit(30),
                TextColumn::make('createdBy.name')->label('Created By')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('assign_referrer')
                    ->label('Assign Referrer')
                    ->visible(fn (): bool => auth()->user()?->can('assign', CustomerReferral::class) === true)
                    ->form($this->referrerActionForm())
                    ->action(function (array $data): void {
                        app(CustomerReferralService::class)->assign(
                            $this->getOwnerRecord(),
                            Referrer::query()->findOrFail($data['referrer_id']),
                            $data,
                            auth()->id(),
                        );
                    }),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('change')
                        ->label('Change Referrer')
                        ->visible(fn (): bool => auth()->user()?->can('change', CustomerReferral::class) === true)
                        ->form([
                            ...$this->referrerActionForm(),
                            Textarea::make('reason')->required(),
                        ])
                        ->action(function (array $data): void {
                            app(CustomerReferralService::class)->change(
                                $this->getOwnerRecord(),
                                Referrer::query()->findOrFail($data['referrer_id']),
                                $data,
                                auth()->id(),
                            );
                        })
                ),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->visible(fn ($record): bool => $record->status === CustomerReferralStatus::ACTIVE && (auth()->user()?->can('suspend', $record) ?? false))
                    ->form([Textarea::make('reason')->required()])
                    ->action(fn ($record, array $data) => app(CustomerReferralService::class)->suspend($record, $data['reason'], auth()->id())),
                Action::make('reactivate')
                    ->visible(fn ($record): bool => $record->status === CustomerReferralStatus::SUSPENDED && (auth()->user()?->can('reactivate', $record) ?? false))
                    ->form([DatePicker::make('effective_from')->default(today())])
                    ->action(fn ($record, array $data) => app(CustomerReferralService::class)->reactivate($record, $data['effective_from'] ? Carbon::parse($data['effective_from']) : null, auth()->id())),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('end')
                        ->visible(fn ($record): bool => in_array($record->status, [CustomerReferralStatus::ACTIVE, CustomerReferralStatus::SUSPENDED], true) && (auth()->user()?->can('end', $record) ?? false))
                        ->form([
                            DatePicker::make('effective_to')->default(today()),
                            Textarea::make('reason')->required(),
                        ])
                        ->action(fn ($record, array $data) => app(CustomerReferralService::class)->end($record, $data['reason'], $data['effective_to'] ? Carbon::parse($data['effective_to']) : null, auth()->id()))
                ),
                SensitiveActionPasswordConfirmation::protect(
                    Action::make('cancel')
                        ->visible(fn ($record): bool => $record->status !== CustomerReferralStatus::CANCELLED && (auth()->user()?->can('cancel', $record) ?? false))
                        ->form([Textarea::make('reason')->required()])
                        ->action(fn ($record, array $data) => app(CustomerReferralService::class)->cancel($record, $data['reason'], auth()->id()))
                ),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function referrerActionForm(): array
    {
        return [
            Select::make('referrer_id')
                ->label('Referrer')
                ->options(fn (): array => Referrer::query()->where('is_active', true)->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('effective_from')->required()->default(today()),
            DatePicker::make('referred_at')->default(today()),
            TextInput::make('referral_source')->maxLength(255),
            Textarea::make('notes')->rows(3),
        ];
    }
}
