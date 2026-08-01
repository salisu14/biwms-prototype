<?php

declare(strict_types=1);

namespace App\Filament\Resources\Referrers\Schemas;

use App\Models\Referrer;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionApprovalBalanceService;
use App\Services\Sales\ReferralCommissions\ReferrerCommissionPaymentBalanceService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferrerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'xl' => 2,
                ])->schema([
                    Section::make('General Information')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('code')->weight('bold')->copyable(),
                            TextEntry::make('name')->weight('bold'),
                            TextEntry::make('type')->badge()->color(fn ($state) => $state?->color()),
                            TextEntry::make('linked_entity')
                                ->label('Linked Entity')
                                ->state(fn (Referrer $record): string => $record->linkedEntityLabel()),
                            IconEntry::make('is_active')->boolean(),
                            IconEntry::make('commission_eligible')->boolean(),
                        ]),

                    Section::make('Contact Details')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('phone')->icon('heroicon-m-phone')->placeholder('—'),
                            TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()->placeholder('—'),
                            TextEntry::make('city')->placeholder('—'),
                            TextEntry::make('state')->placeholder('—'),
                            TextEntry::make('country')->placeholder('—'),
                            TextEntry::make('address')->columnSpanFull()->placeholder('—'),
                        ]),

                    Section::make('Active Commission Plan')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('active_commission_plan')
                                ->label('Plan')
                                ->state(fn (Referrer $record): string => self::activeCommissionPlanAssignment($record)?->plan?->name ?? 'No active plan'),
                            TextEntry::make('active_commission_plan_code')
                                ->label('Plan Code')
                                ->copyable()
                                ->state(fn (Referrer $record): ?string => self::activeCommissionPlanAssignment($record)?->plan?->code)
                                ->placeholder('—'),
                            TextEntry::make('active_commission_plan_effective_from')
                                ->label('Effective From')
                                ->date()
                                ->state(fn (Referrer $record) => self::activeCommissionPlanAssignment($record)?->effective_from)
                                ->placeholder('—'),
                            TextEntry::make('active_commission_plan_reason')
                                ->label('Assignment Reason')
                                ->state(fn (Referrer $record): ?string => self::activeCommissionPlanAssignment($record)?->assignment_reason)
                                ->placeholder('—'),
                        ]),
                ]),

                Section::make('Commission Review & Settlement Readiness')
                    ->description('Ledger and allocation-derived balances by currency.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('approval_ready_balances')
                            ->label('Balances')
                            ->state(fn (Referrer $record): string => self::approvalBalances($record))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Commission Payment Balance')
                    ->description('Settlement and payment-application derived balances.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payment_ready_balances')
                            ->label('Payment Balances')
                            ->state(fn (Referrer $record): string => self::paymentBalances($record))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')->markdown()->placeholder('No notes recorded.'),
                    ]),
            ]);
    }

    private static function activeCommissionPlanAssignment(Referrer $referrer): ?ReferrerCommissionPlanAssignment
    {
        return $referrer->activeCommissionPlanAssignment()
            ->with('plan')
            ->first();
    }

    private static function approvalBalances(Referrer $referrer): string
    {
        $balances = app(ReferrerCommissionApprovalBalanceService::class)->balances(['referrer_id' => $referrer->id]);

        if ($balances === []) {
            return 'No commission review balances yet.';
        }

        return collect($balances)
            ->map(function (array $balance, string $currency): string {
                return "**{$currency}**  \n"
                    ."Open accrual: {$balance['open_accrual']}  \n"
                    ."Under review: {$balance['under_review']}  \n"
                    ."Held: {$balance['held']}  \n"
                    ."Disputed: {$balance['disputed']}  \n"
                    ."Forfeited: {$balance['forfeited']}  \n"
                    ."Approved: {$balance['approved']}  \n"
                    ."Allocated for future settlement: {$balance['allocated_to_settlement']}  \n"
                    ."Locked for future payment: {$balance['locked_for_future_payment']}  \n"
                    ."Net unallocated: {$balance['net_unallocated']}";
            })
            ->implode("\n\n");
    }

    private static function paymentBalances(Referrer $referrer): string
    {
        $balances = app(ReferrerCommissionPaymentBalanceService::class)->balances(['referrer_id' => $referrer->id]);

        if ($balances === []) {
            return 'No commission payment balances yet.';
        }

        return collect($balances)
            ->map(fn (array $balance): string => "**{$balance['referrer_name']}**  \n"
                ."Settled: {$balance['settled_amount']}  \n"
                ."Paid: {$balance['paid_amount']}  \n"
                ."Payment reversed: {$balance['payment_reversed_amount']}  \n"
                ."Outstanding: {$balance['outstanding_amount']}  \n"
                ."Recovery required: {$balance['recovery_required_amount']}")
            ->implode("\n\n");
    }
}
