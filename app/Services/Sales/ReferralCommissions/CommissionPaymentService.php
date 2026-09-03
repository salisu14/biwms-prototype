<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\BankAccountLedgerEntryType;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionLiabilityPostingStatus;
use App\Enums\CommissionPaymentApplicationStatus;
use App\Enums\CommissionPaymentApplicationType;
use App\Enums\CommissionPaymentBatchStatus;
use App\Enums\CommissionPaymentLineStatus;
use App\Enums\CommissionPaymentMethod;
use App\Enums\CommissionSettlementBatchStatus;
use App\Enums\PettyCashTransactionType;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionLiabilityPosting;
use App\Models\CommissionPaymentApplication;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionPaymentLine;
use App\Models\CommissionSettlementAllocation;
use App\Models\CommissionSettlementBatch;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\BankAccountLedgerService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\NumberSeriesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionPaymentService
{
    public function __construct(
        private readonly CommissionPostingSetupResolver $setupResolver,
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly BankAccountLedgerService $bankAccountLedgerService,
        private readonly AuditTrailService $auditTrailService,
        private readonly NumberSeriesService $numberSeriesService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBatchFromSettlement(CommissionSettlementBatch $settlementBatch, array $data, User $actor): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.create');

        return DB::transaction(function () use ($settlementBatch, $data, $actor): CommissionPaymentBatch {
            $batch = CommissionSettlementBatch::query()
                ->with(['lines.referrer', 'lines.allocations'])
                ->lockForUpdate()
                ->findOrFail($settlementBatch->id);

            if ($batch->status !== CommissionSettlementBatchStatus::Locked) {
                throw new RuntimeException('Commission payments can only be prepared from locked settlement batches.');
            }

            $this->assertLiabilityPosted($batch);
            $method = CommissionPaymentMethod::tryFrom((string) ($data['payment_method'] ?? CommissionPaymentMethod::BankTransfer->value));
            $this->assertSupportedMethod($method);

            $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());
            $postingDate = (string) ($data['posting_date'] ?? $paymentDate);
            $idempotencyKey = hash('sha256', implode('|', [
                'commission-payment-batch',
                $batch->id,
                $method->value,
                $paymentDate,
                $postingDate,
                $data['bank_account_id'] ?? '',
                $data['cash_account_id'] ?? '',
                $data['external_reference'] ?? '',
            ]));

            $existing = CommissionPaymentBatch::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh(['lines']);
            }

            $paymentBatch = CommissionPaymentBatch::query()->create([
                'business_id' => $batch->business_id,
                'batch_number' => $data['batch_number'] ?? $this->nextBatchNumber(),
                'commission_settlement_batch_id' => $batch->id,
                'currency_code' => $batch->currency_code,
                'payment_date' => $paymentDate,
                'posting_date' => $postingDate,
                'payment_method' => $method,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_account_id' => $data['cash_account_id'] ?? null,
                'status' => CommissionPaymentBatchStatus::Draft,
                'description' => $data['description'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['settlement_number' => $batch->settlement_number],
            ]);

            $lineAmounts = $data['line_amounts'] ?? [];
            $total = 0.0;
            $referrers = [];

            foreach ($batch->lines as $settlementLine) {
                $approvedAmount = (float) $settlementLine->net_settlement_amount;
                $previouslyPaid = $this->paidAmountForSettlementLine((int) $settlementLine->id);
                $outstanding = round($approvedAmount - $previouslyPaid, 4);
                if ($outstanding <= 0) {
                    continue;
                }

                $requestedAmount = isset($lineAmounts[$settlementLine->id]) ? (float) $lineAmounts[$settlementLine->id] : $outstanding;
                if ($requestedAmount <= 0) {
                    continue;
                }
                if ($requestedAmount - $outstanding > 0.0001) {
                    throw new RuntimeException('Commission payment line exceeds available settlement amount.');
                }

                $total += $requestedAmount;
                $referrers[(int) $settlementLine->referrer_id] = true;

                CommissionPaymentLine::query()->create([
                    'business_id' => $batch->business_id,
                    'commission_payment_batch_id' => $paymentBatch->id,
                    'commission_settlement_batch_id' => $batch->id,
                    'commission_settlement_line_id' => $settlementLine->id,
                    'referrer_id' => $settlementLine->referrer_id,
                    'currency_code' => $batch->currency_code,
                    'approved_amount' => $approvedAmount,
                    'previously_paid_amount' => $previouslyPaid,
                    'payment_amount' => $requestedAmount,
                    'remaining_amount' => round($outstanding - $requestedAmount, 4),
                    'payment_method' => $method,
                    'beneficiary_name' => $settlementLine->referrer?->name,
                    'masked_payment_reference' => $this->maskedPaymentReference($settlementLine->referrer?->email ?? $settlementLine->referrer?->phone),
                    'external_reference' => $data['external_reference'] ?? null,
                    'status' => CommissionPaymentLineStatus::Draft,
                    'idempotency_key' => hash('sha256', 'commission-payment-line|'.$paymentBatch->id.'|'.$settlementLine->id),
                    'snapshot' => [
                        'settlement_line_id' => $settlementLine->id,
                        'referrer_code' => $settlementLine->referrer?->code,
                        'referrer_name' => $settlementLine->referrer?->name,
                    ],
                ]);
            }

            if ($total <= 0) {
                throw new RuntimeException('No payable commission settlement lines were found.');
            }

            CommissionPaymentBatch::allowServiceMutation(fn (): bool => $paymentBatch->forceFill([
                'total_amount' => $total,
                'line_count' => $paymentBatch->lines()->count(),
                'referrer_count' => count($referrers),
            ])->save());

            $this->auditTrailService->recordGeneric('commission_payment', 'payment_batch_created', $paymentBatch, userId: $actor->id);

            return $paymentBatch->fresh(['lines']);
        });
    }

    public function prepare(CommissionPaymentBatch $batch, User $actor): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.prepare');

        return $this->transition($batch, [CommissionPaymentBatchStatus::Draft], [
            'status' => CommissionPaymentBatchStatus::Prepared,
            'prepared_at' => now(),
            'prepared_by' => $actor->id,
        ], $actor, 'payment_batch_prepared', CommissionPaymentLineStatus::Eligible);
    }

    public function submit(CommissionPaymentBatch $batch, User $actor): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.submit');

        return $this->transition($batch, [CommissionPaymentBatchStatus::Prepared], [
            'status' => CommissionPaymentBatchStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $actor->id,
        ], $actor, 'payment_batch_submitted');
    }

    public function approve(CommissionPaymentBatch $batch, User $actor): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.approve');

        return DB::transaction(function () use ($batch, $actor): CommissionPaymentBatch {
            $locked = CommissionPaymentBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== CommissionPaymentBatchStatus::Submitted) {
                throw new RuntimeException('Only submitted commission payment batches can be approved.');
            }
            if ((int) $locked->prepared_by === (int) $actor->id || (int) $locked->submitted_by === (int) $actor->id) {
                throw new RuntimeException('Preparer or submitter cannot approve their own commission payment batch.');
            }

            CommissionPaymentBatch::allowServiceMutation(fn (): bool => $locked->forceFill([
                'status' => CommissionPaymentBatchStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save());
            CommissionPaymentLine::allowServiceMutation(fn (): int => $locked->lines()->update(['status' => CommissionPaymentLineStatus::Approved->value]));
            $this->auditTrailService->recordGeneric('commission_payment', 'payment_batch_approved', $locked, userId: $actor->id);

            return $locked->fresh(['lines']);
        });
    }

    public function post(CommissionPaymentBatch $batch, User $actor): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.post');

        return DB::transaction(function () use ($batch, $actor): CommissionPaymentBatch {
            $locked = CommissionPaymentBatch::query()
                ->with(['settlementBatch.lines.allocations', 'lines'])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            if ($locked->status === CommissionPaymentBatchStatus::Posted) {
                return $locked->fresh(['lines', 'applications', 'postingTransaction']);
            }
            if ($locked->status !== CommissionPaymentBatchStatus::Approved) {
                throw new RuntimeException('Only approved commission payment batches can be posted.');
            }

            $this->assertLiabilityPosted($locked->settlementBatch);
            $accounts = $this->setupResolver->liabilityAccounts($locked->business_id);
            $cashAccountId = $this->paymentCashAccountId($locked);
            $amount = (string) $locked->total_amount;

            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $accounts['payable_account_id'],
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => 'Commission payable cleared '.$locked->batch_number,
                ],
                [
                    'account_id' => $cashAccountId,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => 'Commission payment '.$locked->batch_number,
                ],
            ], [
                'business_id' => $locked->business_id,
                'posting_date' => $locked->posting_date,
                'document_type' => 'COMMISSION_PAYMENT',
                'document_number' => $locked->batch_number,
                'source_module' => 'sales',
                'source_type' => SourceType::COMMISSION->value,
                'source_id' => $locked->id,
                'source_number' => $locked->batch_number,
                'description' => 'Commission payment batch '.$locked->batch_number,
                'currency_code' => $locked->currency_code,
                'actor_id' => $actor->id,
                'idempotency_key' => hash('sha256', 'gl|commission-payment|'.$locked->id),
            ]);

            $this->postCashOrBankLedger($locked, $actor);
            $this->createApplicationsAndLedgerEntries($locked, $actor, $transaction->id);

            CommissionPaymentBatch::allowServiceMutation(fn (): bool => $locked->forceFill([
                'status' => CommissionPaymentBatchStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'posting_transaction_id' => $transaction->id,
            ])->save());
            CommissionPaymentLine::allowServiceMutation(fn (): int => $locked->lines()->update([
                'status' => CommissionPaymentLineStatus::Posted->value,
                'posting_transaction_id' => $transaction->id,
            ]));

            $this->auditTrailService->recordPayment($locked, 'posted', $actor->id, 'COMMISSION_PAYMENT', $locked->batch_number, [
                'posting_transaction_id' => $transaction->id,
                'settlement_batch_id' => $locked->commission_settlement_batch_id,
            ]);

            return $locked->fresh(['lines', 'applications', 'postingTransaction']);
        });
    }

    public function reject(CommissionPaymentBatch $batch, User $actor, string $reason): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.reject');

        return $this->transition($batch, [CommissionPaymentBatchStatus::Submitted], [
            'status' => CommissionPaymentBatchStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $actor->id,
            'rejection_reason' => $reason,
        ], $actor, 'payment_batch_rejected');
    }

    public function cancel(CommissionPaymentBatch $batch, User $actor, string $reason): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.cancel');

        return $this->transition($batch, [CommissionPaymentBatchStatus::Draft, CommissionPaymentBatchStatus::Prepared, CommissionPaymentBatchStatus::Rejected], [
            'status' => CommissionPaymentBatchStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancellation_reason' => $reason,
        ], $actor, 'payment_batch_cancelled');
    }

    public function paidAmountForSettlementLine(int $settlementLineId): float
    {
        $payment = (float) CommissionPaymentApplication::query()
            ->whereHas('line', fn ($query) => $query->where('commission_settlement_line_id', $settlementLineId))
            ->where('status', CommissionPaymentApplicationStatus::Applied)
            ->where('application_type', CommissionPaymentApplicationType::Payment)
            ->sum('applied_amount');

        $reversal = (float) CommissionPaymentApplication::query()
            ->whereHas('line', fn ($query) => $query->where('commission_settlement_line_id', $settlementLineId))
            ->where('status', CommissionPaymentApplicationStatus::Applied)
            ->where('application_type', CommissionPaymentApplicationType::Reversal)
            ->sum('applied_amount');

        return round($payment - $reversal, 4);
    }

    /**
     * @param  array<int, CommissionPaymentBatchStatus>  $allowedStatuses
     * @param  array<string, mixed>  $attributes
     */
    private function transition(CommissionPaymentBatch $batch, array $allowedStatuses, array $attributes, User $actor, string $auditAction, ?CommissionPaymentLineStatus $lineStatus = null): CommissionPaymentBatch
    {
        return DB::transaction(function () use ($batch, $allowedStatuses, $attributes, $actor, $auditAction, $lineStatus): CommissionPaymentBatch {
            $locked = CommissionPaymentBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, $allowedStatuses, true)) {
                throw new RuntimeException('Commission payment batch status does not allow this transition.');
            }

            CommissionPaymentBatch::allowServiceMutation(fn (): bool => $locked->forceFill($attributes)->save());
            if ($lineStatus) {
                CommissionPaymentLine::allowServiceMutation(fn (): int => $locked->lines()->update(['status' => $lineStatus->value]));
            }
            $this->auditTrailService->recordGeneric('commission_payment', $auditAction, $locked, userId: $actor->id);

            return $locked->fresh(['lines']);
        });
    }

    private function assertLiabilityPosted(CommissionSettlementBatch $batch): void
    {
        $exists = CommissionLiabilityPosting::query()
            ->where('commission_settlement_batch_id', $batch->id)
            ->where('status', CommissionLiabilityPostingStatus::Posted)
            ->exists();

        if (! $exists) {
            throw new RuntimeException('Commission liability must be posted before payment preparation.');
        }
    }

    private function assertSupportedMethod(?CommissionPaymentMethod $method): void
    {
        if (! in_array($method, [CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cash, CommissionPaymentMethod::Cheque], true)) {
            throw new RuntimeException('Commission payment method is not supported until payment setup is configured.');
        }
    }

    private function paymentCashAccountId(CommissionPaymentBatch $batch): int
    {
        return match ($batch->payment_method) {
            CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cheque => (int) ($batch->bankAccount?->gl_account_id ?: throw new RuntimeException('A bank account with a G/L account is required.')),
            CommissionPaymentMethod::Cash => (int) ($batch->cashAccount?->chart_of_account_id ?: throw new RuntimeException('A petty cash fund with a G/L account is required.')),
            default => throw new RuntimeException('Unsupported commission payment method.'),
        };
    }

    private function postCashOrBankLedger(CommissionPaymentBatch $batch, User $actor): void
    {
        if (in_array($batch->payment_method, [CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cheque], true)) {
            $bankAccount = BankAccount::query()->lockForUpdate()->findOrFail($batch->bank_account_id);
            $existing = $bankAccount->ledgerEntries()
                ->where('source_type', 'commission_payment')
                ->where('source_id', $batch->id)
                ->exists();

            if (! $existing) {
                $this->bankAccountLedgerService->postPayment($bankAccount, [
                    'amount' => $batch->total_amount,
                    'posting_date' => $batch->posting_date,
                    'document_date' => $batch->payment_date,
                    'document_type' => 'commission_payment',
                    'document_no' => $batch->batch_number,
                    'description' => 'Commission payment batch '.$batch->batch_number,
                    'entry_type' => $batch->payment_method === CommissionPaymentMethod::Cheque ? BankAccountLedgerEntryType::CHECK : BankAccountLedgerEntryType::WITHDRAWAL,
                    'currency_code' => $batch->currency_code,
                    'source_type' => 'commission_payment',
                    'source_id' => $batch->id,
                    'source_no' => $batch->batch_number,
                    'user_id' => $actor->id,
                ]);
            }

            return;
        }

        $fund = PettyCashFund::query()->lockForUpdate()->findOrFail($batch->cash_account_id);
        if ((float) $fund->current_balance < (float) $batch->total_amount) {
            throw new RuntimeException('Insufficient petty cash balance for commission payment.');
        }
        $newBalance = round((float) $fund->current_balance - (float) $batch->total_amount, 4);
        $fund->update(['current_balance' => $newBalance]);
        PettyCashTransaction::query()->firstOrCreate(
            ['reference_number' => $batch->batch_number],
            [
                'petty_cash_fund_id' => $fund->id,
                'transaction_number' => 'CPC-'.$batch->batch_number,
                'date' => $batch->posting_date,
                'type' => PettyCashTransactionType::PAYMENT,
                'amount' => $batch->total_amount,
                'running_balance' => $newBalance,
                'description' => 'Commission payment batch '.$batch->batch_number,
            ],
        );
    }

    private function createApplicationsAndLedgerEntries(CommissionPaymentBatch $batch, User $actor, int $postingTransactionId): void
    {
        $batch->lines->each(function (CommissionPaymentLine $line) use ($batch, $actor, $postingTransactionId): void {
            $remainingToApply = (float) $line->payment_amount;
            $allocations = CommissionSettlementAllocation::query()
                ->where('commission_settlement_line_id', $line->commission_settlement_line_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                if ($remainingToApply <= 0) {
                    break;
                }

                $alreadyPaid = (float) CommissionPaymentApplication::query()
                    ->where('commission_settlement_allocation_id', $allocation->id)
                    ->where('application_type', CommissionPaymentApplicationType::Payment)
                    ->sum('applied_amount')
                    - (float) CommissionPaymentApplication::query()
                        ->where('commission_settlement_allocation_id', $allocation->id)
                        ->where('application_type', CommissionPaymentApplicationType::Reversal)
                        ->sum('applied_amount');

                $available = round((float) $allocation->allocated_amount - $alreadyPaid, 4);
                if ($available <= 0) {
                    continue;
                }

                $applyAmount = min($remainingToApply, $available);
                $ledgerEntry = CommissionLedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => hash('sha256', 'commission-payment-ledger|'.$line->id.'|'.$allocation->id)],
                    [
                        'business_id' => $batch->business_id,
                        'entry_type' => CommissionLedgerEntryType::Payment,
                        'referrer_id' => $line->referrer_id,
                        'source_type' => CommissionPaymentBatch::class,
                        'source_id' => $batch->id,
                        'source_line_id' => $line->id,
                        'source_number' => $batch->batch_number,
                        'posting_date' => $batch->posting_date,
                        'currency_code' => $batch->currency_code,
                        'amount' => -abs($applyAmount),
                        'base_amount' => -abs($applyAmount),
                        'status' => CommissionLedgerEntryStatus::Open,
                        'description' => 'Commission paid',
                        'metadata' => ['posting_transaction_id' => $postingTransactionId],
                        'created_by' => $actor->id,
                    ],
                );

                CommissionPaymentApplication::query()->firstOrCreate(
                    ['idempotency_key' => hash('sha256', 'commission-payment-application|'.$line->id.'|'.$allocation->id)],
                    [
                        'business_id' => $batch->business_id,
                        'commission_payment_batch_id' => $batch->id,
                        'commission_payment_line_id' => $line->id,
                        'commission_settlement_allocation_id' => $allocation->id,
                        'commission_ledger_entry_id' => $ledgerEntry->id,
                        'referrer_id' => $line->referrer_id,
                        'currency_code' => $batch->currency_code,
                        'applied_amount' => $applyAmount,
                        'application_type' => CommissionPaymentApplicationType::Payment,
                        'status' => CommissionPaymentApplicationStatus::Applied,
                        'posting_date' => $batch->posting_date,
                        'created_by' => $actor->id,
                    ],
                );

                $remainingToApply = round($remainingToApply - $applyAmount, 4);
            }

            if ($remainingToApply > 0.0001) {
                throw new RuntimeException('Commission payment exceeds remaining settlement allocation.');
            }
        });
    }

    private function nextBatchNumber(): string
    {
        try {
            return $this->numberSeriesService->getNextNo('COMM-PAY');
        } catch (\Throwable) {
            return 'CPB-'.str_pad((string) (((int) CommissionPaymentBatch::query()->max('id')) + 1), 6, '0', STR_PAD_LEFT);
        }
    }

    private function maskedPaymentReference(?string $reference): ?string
    {
        if (! $reference) {
            return null;
        }

        $suffix = substr($reference, -4);

        return str_repeat('*', max(strlen($reference) - 4, 0)).$suffix;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Missing permission [{$permission}].");
        }
    }
}
