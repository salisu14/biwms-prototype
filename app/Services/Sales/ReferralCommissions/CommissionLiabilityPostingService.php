<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionLiabilityPostingStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Enums\SourceType;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionLiabilityPosting;
use App\Models\CommissionSettlementBatch;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\NumberSeriesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommissionLiabilityPostingService
{
    public function __construct(
        private readonly CommissionPostingSetupResolver $setupResolver,
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly AuditTrailService $auditTrailService,
        private readonly NumberSeriesService $numberSeriesService,
    ) {}

    public function post(CommissionSettlementBatch $settlementBatch, User $actor, ?string $postingDate = null): CommissionLiabilityPosting
    {
        $this->authorize($actor, 'sales.commission_liability.post');

        return DB::transaction(function () use ($settlementBatch, $actor, $postingDate): CommissionLiabilityPosting {
            $batch = CommissionSettlementBatch::query()
                ->with(['lines.referrer', 'liabilityPosting'])
                ->lockForUpdate()
                ->findOrFail($settlementBatch->id);

            if ($batch->status !== CommissionSettlementBatchStatus::Locked) {
                throw new RuntimeException('Only locked commission settlement batches can be recognized as liabilities.');
            }

            $existing = CommissionLiabilityPosting::query()
                ->where('commission_settlement_batch_id', $batch->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === CommissionLiabilityPostingStatus::Posted) {
                return $existing->fresh(['postingTransaction']);
            }

            if ($existing && $existing->status !== CommissionLiabilityPostingStatus::Failed) {
                throw new RuntimeException('Commission liability posting already exists for this settlement batch.');
            }

            $accounts = $this->setupResolver->liabilityAccounts($batch->business_id);
            $netAmount = (string) $batch->total_net_amount;
            if ((float) $netAmount <= 0) {
                throw new RuntimeException('Commission liability amount must be greater than zero.');
            }

            $liability = $existing ?? CommissionLiabilityPosting::query()->create([
                'business_id' => $batch->business_id,
                'commission_settlement_batch_id' => $batch->id,
                'currency_code' => $batch->currency_code,
                'posting_date' => $postingDate ?? now()->toDateString(),
                'document_number' => $this->nextDocumentNumber($batch),
                'status' => CommissionLiabilityPostingStatus::Pending,
                'gross_amount' => $batch->total_gross_amount,
                'withholding_amount' => 0,
                'net_liability_amount' => $netAmount,
                'idempotency_key' => hash('sha256', 'commission-liability|'.$batch->id.'|'.$batch->snapshot_version),
                'metadata' => [
                    'settlement_number' => $batch->settlement_number,
                    'line_count' => $batch->line_count,
                ],
            ]);

            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $accounts['expense_account_id'],
                    'debit_amount' => $netAmount,
                    'credit_amount' => 0,
                    'description' => 'Commission expense '.$batch->settlement_number,
                ],
                [
                    'account_id' => $accounts['payable_account_id'],
                    'debit_amount' => 0,
                    'credit_amount' => $netAmount,
                    'description' => 'Commission payable '.$batch->settlement_number,
                ],
            ], [
                'business_id' => $batch->business_id,
                'posting_date' => $liability->posting_date,
                'document_type' => 'COMMISSION_LIABILITY',
                'document_number' => $liability->document_number,
                'source_module' => 'sales',
                'source_type' => SourceType::COMMISSION->value,
                'source_id' => $liability->id,
                'source_number' => $liability->document_number,
                'description' => 'Commission liability recognition '.$batch->settlement_number,
                'currency_code' => $batch->currency_code,
                'actor_id' => $actor->id,
                'idempotency_key' => hash('sha256', 'gl|commission-liability|'.$liability->id),
            ]);

            $batch->lines->each(function ($line) use ($batch, $actor, $liability): void {
                CommissionLedgerEntry::query()->firstOrCreate(
                    [
                        'idempotency_key' => hash('sha256', 'commission-ledger-liability|'.$liability->id.'|'.$line->id),
                    ],
                    [
                        'business_id' => $batch->business_id,
                        'entry_type' => CommissionLedgerEntryType::LiabilityRecognition,
                        'referrer_id' => $line->referrer_id,
                        'source_type' => CommissionLiabilityPosting::class,
                        'source_id' => $liability->id,
                        'source_line_id' => $line->id,
                        'source_number' => $liability->document_number,
                        'posting_date' => $liability->posting_date,
                        'currency_code' => $batch->currency_code,
                        'amount' => $line->net_settlement_amount,
                        'base_amount' => $line->net_settlement_amount,
                        'status' => CommissionLedgerEntryStatus::ApprovedForFuturePayment,
                        'description' => 'Commission liability recognized',
                        'metadata' => ['settlement_batch_id' => $batch->id],
                        'created_by' => $actor->id,
                    ],
                );
            });

            CommissionLiabilityPosting::allowServiceMutation(fn (): bool => $liability->forceFill([
                'status' => CommissionLiabilityPostingStatus::Posted,
                'posting_transaction_id' => $transaction->id,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ])->save());

            $this->auditTrailService->recordPosting(
                auditable: $liability,
                userId: $actor->id,
                documentType: 'COMMISSION_LIABILITY',
                documentNo: $liability->document_number,
                metadata: ['settlement_batch_id' => $batch->id, 'posting_transaction_id' => $transaction->id],
                description: 'Commission liability recognized for '.$batch->settlement_number,
            );

            return $liability->fresh(['postingTransaction']);
        });
    }

    public function reverse(CommissionLiabilityPosting $posting, User $actor, ?string $reason = null): CommissionLiabilityPosting
    {
        $this->authorize($actor, 'sales.commission_liability.reverse');

        return DB::transaction(function () use ($posting, $actor, $reason): CommissionLiabilityPosting {
            $liability = CommissionLiabilityPosting::query()->lockForUpdate()->findOrFail($posting->id);
            if ($liability->status === CommissionLiabilityPostingStatus::Reversed) {
                return $liability->fresh(['reversalPostingTransaction']);
            }
            if ($liability->status !== CommissionLiabilityPostingStatus::Posted) {
                throw new RuntimeException('Only posted commission liabilities can be reversed.');
            }

            $accounts = $this->setupResolver->liabilityAccounts($liability->business_id);
            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $accounts['payable_account_id'],
                    'debit_amount' => $liability->net_liability_amount,
                    'credit_amount' => 0,
                    'description' => 'Reverse commission payable '.$liability->document_number,
                ],
                [
                    'account_id' => $accounts['expense_account_id'],
                    'debit_amount' => 0,
                    'credit_amount' => $liability->net_liability_amount,
                    'description' => 'Reverse commission expense '.$liability->document_number,
                ],
            ], [
                'business_id' => $liability->business_id,
                'posting_date' => now()->toDateString(),
                'document_type' => 'COMMISSION_LIABILITY_REVERSAL',
                'document_number' => 'REV-'.$liability->document_number,
                'source_module' => 'sales',
                'source_type' => SourceType::COMMISSION->value,
                'source_id' => $liability->id,
                'source_number' => $liability->document_number,
                'description' => $reason ?: 'Commission liability reversal',
                'currency_code' => $liability->currency_code,
                'actor_id' => $actor->id,
                'idempotency_key' => hash('sha256', 'gl|commission-liability-reversal|'.$liability->id),
            ]);

            CommissionLiabilityPosting::allowServiceMutation(fn (): bool => $liability->forceFill([
                'status' => CommissionLiabilityPostingStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_posting_transaction_id' => $transaction->id,
            ])->save());

            $this->auditTrailService->recordReversal($liability, $actor->id, 'COMMISSION_LIABILITY', $liability->document_number, [
                'reason' => $reason,
                'reversal_posting_transaction_id' => $transaction->id,
            ]);

            return $liability->fresh(['reversalPostingTransaction']);
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("Missing permission [{$permission}].");
        }
    }

    private function nextDocumentNumber(CommissionSettlementBatch $batch): string
    {
        try {
            return $this->numberSeriesService->getNextNo('COMM-LIAB');
        } catch (\Throwable) {
            return 'CLP-'.$batch->settlement_number;
        }
    }
}
