<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionPaymentApplicationStatus;
use App\Enums\CommissionPaymentApplicationType;
use App\Enums\CommissionPaymentBatchStatus;
use App\Enums\CommissionPaymentLineStatus;
use App\Enums\CommissionPaymentMethod;
use App\Models\BankAccount;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionPaymentApplication;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionPaymentLine;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\BankAccountLedgerService;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionPaymentReversalService
{
    public function __construct(
        private readonly CommissionPostingSetupResolver $setupResolver,
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly BankAccountLedgerService $bankAccountLedgerService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function reverseBatch(CommissionPaymentBatch $batch, User $actor, ?string $reason = null): CommissionPaymentBatch
    {
        $this->authorize($actor, 'sales.commission_payment_batch.reverse');

        return DB::transaction(function () use ($batch, $actor, $reason): CommissionPaymentBatch {
            $locked = CommissionPaymentBatch::query()->with(['lines', 'applications'])->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === CommissionPaymentBatchStatus::Reversed) {
                return $locked->fresh(['lines', 'applications']);
            }
            if ($locked->status !== CommissionPaymentBatchStatus::Posted) {
                throw new RuntimeException('Only posted commission payment batches can be reversed.');
            }

            $accounts = $this->setupResolver->liabilityAccounts($locked->business_id);
            $cashAccountId = $this->paymentCashAccountId($locked);
            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $cashAccountId,
                    'debit_amount' => $locked->total_amount,
                    'credit_amount' => 0,
                    'description' => 'Reverse commission payment '.$locked->batch_number,
                ],
                [
                    'account_id' => $accounts['payable_account_id'],
                    'debit_amount' => 0,
                    'credit_amount' => $locked->total_amount,
                    'description' => 'Reopen commission payable '.$locked->batch_number,
                ],
            ], [
                'business_id' => $locked->business_id,
                'posting_date' => now()->toDateString(),
                'document_type' => 'COMMISSION_PAYMENT_REVERSAL',
                'document_number' => 'REV-'.$locked->batch_number,
                'source_module' => 'sales',
                'source_type' => 'commission_payment',
                'source_id' => $locked->id,
                'source_number' => $locked->batch_number,
                'description' => $reason ?: 'Commission payment reversal',
                'currency_code' => $locked->currency_code,
                'actor_id' => $actor->id,
                'idempotency_key' => hash('sha256', 'gl|commission-payment-reversal|'.$locked->id),
            ]);

            $this->reverseCashOrBankLedger($locked, $actor);
            $this->createReversalApplications($locked, $actor);

            CommissionPaymentBatch::allowServiceMutation(fn (): bool => $locked->forceFill([
                'status' => CommissionPaymentBatchStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
            ])->save());
            CommissionPaymentLine::allowServiceMutation(fn (): int => $locked->lines()->update(['status' => CommissionPaymentLineStatus::Reversed->value]));

            $this->auditTrailService->recordReversal($locked, $actor->id, 'COMMISSION_PAYMENT', $locked->batch_number, [
                'reason' => $reason,
                'posting_transaction_id' => $transaction->id,
            ]);

            return $locked->fresh(['lines', 'applications']);
        });
    }

    private function reverseCashOrBankLedger(CommissionPaymentBatch $batch, User $actor): void
    {
        if (in_array($batch->payment_method, [CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cheque], true)) {
            $bankAccount = BankAccount::query()->lockForUpdate()->findOrFail($batch->bank_account_id);
            $this->bankAccountLedgerService->postDeposit($bankAccount, [
                'amount' => $batch->total_amount,
                'posting_date' => now()->toDateString(),
                'document_type' => 'commission_payment_reversal',
                'document_no' => 'REV-'.$batch->batch_number,
                'description' => 'Reverse commission payment batch '.$batch->batch_number,
                'currency_code' => $batch->currency_code,
                'source_type' => 'commission_payment',
                'source_id' => $batch->id,
                'source_no' => $batch->batch_number,
                'user_id' => $actor->id,
            ]);

            return;
        }

        $fund = PettyCashFund::query()->lockForUpdate()->findOrFail($batch->cash_account_id);
        $fund->update(['current_balance' => round((float) $fund->current_balance + (float) $batch->total_amount, 4)]);
    }

    private function createReversalApplications(CommissionPaymentBatch $batch, User $actor): void
    {
        CommissionPaymentApplication::query()
            ->where('commission_payment_batch_id', $batch->id)
            ->where('application_type', CommissionPaymentApplicationType::Payment)
            ->get()
            ->each(function (CommissionPaymentApplication $application) use ($batch, $actor): void {
                $ledgerEntry = CommissionLedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => hash('sha256', 'commission-payment-reversal-ledger|'.$application->id)],
                    [
                        'business_id' => $batch->business_id,
                        'entry_type' => CommissionLedgerEntryType::PaymentReversal,
                        'referrer_id' => $application->referrer_id,
                        'source_type' => CommissionPaymentBatch::class,
                        'source_id' => $batch->id,
                        'source_line_id' => $application->commission_payment_line_id,
                        'source_number' => 'REV-'.$batch->batch_number,
                        'posting_date' => now()->toDateString(),
                        'currency_code' => $batch->currency_code,
                        'amount' => abs((float) $application->applied_amount),
                        'base_amount' => abs((float) $application->applied_amount),
                        'status' => CommissionLedgerEntryStatus::Open,
                        'description' => 'Commission payment reversed',
                        'metadata' => ['reverses_application_id' => $application->id],
                        'created_by' => $actor->id,
                    ],
                );

                CommissionPaymentApplication::query()->firstOrCreate(
                    ['idempotency_key' => hash('sha256', 'commission-payment-reversal-application|'.$application->id)],
                    [
                        'business_id' => $batch->business_id,
                        'commission_payment_batch_id' => $batch->id,
                        'commission_payment_line_id' => $application->commission_payment_line_id,
                        'commission_settlement_allocation_id' => $application->commission_settlement_allocation_id,
                        'commission_ledger_entry_id' => $ledgerEntry->id,
                        'referrer_id' => $application->referrer_id,
                        'currency_code' => $batch->currency_code,
                        'applied_amount' => $application->applied_amount,
                        'application_type' => CommissionPaymentApplicationType::Reversal,
                        'status' => CommissionPaymentApplicationStatus::Applied,
                        'reverses_application_id' => $application->id,
                        'posting_date' => now()->toDateString(),
                        'created_by' => $actor->id,
                    ],
                );
            });
    }

    private function paymentCashAccountId(CommissionPaymentBatch $batch): int
    {
        return match ($batch->payment_method) {
            CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cheque => (int) ($batch->bankAccount?->gl_account_id ?: throw new RuntimeException('A bank account with a G/L account is required.')),
            CommissionPaymentMethod::Cash => (int) ($batch->cashAccount?->chart_of_account_id ?: throw new RuntimeException('A petty cash fund with a G/L account is required.')),
            default => throw new RuntimeException('Unsupported commission payment method.'),
        };
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Missing permission [{$permission}].");
        }
    }
}
