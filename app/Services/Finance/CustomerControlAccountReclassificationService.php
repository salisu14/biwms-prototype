<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Models\SubledgerOpeningBalance;
use App\Services\Accounting\ControlAccountAssignmentService;
use App\Services\AuditTrailService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

final class CustomerControlAccountReclassificationService
{
    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly ControlAccountAssignmentService $controlAccountAssignmentService,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * Validate the proposed correction without changing configuration or
     * posting any accounting rows.
     *
     * @return array<string, mixed>
     */
    public function analyzeCustomerReceivables(SubledgerOpeningBalance $opening, ChartOfAccount $targetAccount): array
    {
        return DB::transaction(
            fn (): array => $this->inspect(
                SubledgerOpeningBalance::query()->findOrFail($opening->getKey()),
                ChartOfAccount::query()->findOrFail($targetAccount->getKey()),
            ),
            1,
        );
    }

    /**
     * Reclassify one historical customer opening balance to the approved
     * receivables account without changing the original accounting rows.
     *
     * @return array<string, mixed>
     */
    public function reclassifyCustomerReceivables(
        SubledgerOpeningBalance $opening,
        ChartOfAccount $targetAccount,
        ?int $actorId = null,
        string $reason = 'Correct historical customer receivables account classification',
    ): array {
        return DB::transaction(function () use ($opening, $targetAccount, $actorId, $reason): array {
            $opening = SubledgerOpeningBalance::query()
                ->lockForUpdate()
                ->findOrFail($opening->getKey());
            $targetAccount = ChartOfAccount::query()->lockForUpdate()->findOrFail($targetAccount->getKey());

            $inspection = $this->inspect($opening, $targetAccount);
            $group = CustomerPostingGroup::query()->lockForUpdate()->findOrFail($inspection['posting_group_id']);

            if ((int) $group->receivables_account_id !== $inspection['original_account_id']
                && (int) $group->receivables_account_id !== $targetAccount->id) {
                throw new BusinessException('The customer posting group changed unexpectedly; no reclassification is safe.');
            }

            $this->postingDateValidator->validate($inspection['posting_date']);

            if ((int) $group->receivables_account_id !== $targetAccount->id) {
                $group->receivables_account_id = $targetAccount->id;
                $group->save();
            }

            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $targetAccount->id,
                    'debit_amount' => $inspection['amount'],
                    'credit_amount' => '0',
                    'description' => $reason,
                    'source_type' => SourceType::CUSTOMER->value,
                    'source_number' => $inspection['correction_document_number'],
                ],
                [
                    'account_id' => $inspection['original_account_id'],
                    'debit_amount' => '0',
                    'credit_amount' => $inspection['amount'],
                    'description' => $reason,
                    'source_type' => SourceType::CUSTOMER->value,
                    'source_number' => $inspection['correction_document_number'],
                ],
            ], [
                'business_id' => $opening->business_id,
                'posting_date' => $inspection['posting_date'],
                'document_date' => $inspection['posting_date'],
                'source_module' => 'finance',
                'source_type' => SourceType::CUSTOMER->value,
                'source_id' => $opening->id,
                'source_number' => $inspection['correction_document_number'],
                'document_type' => 'CUSTOMER_CTRL_RECLASS',
                'document_number' => $inspection['correction_document_number'],
                'external_document_number' => $opening->document_number,
                'currency_code' => $opening->currency_code,
                'exchange_rate' => $opening->currency_factor,
                'actor_id' => $actorId,
                'transaction_key' => $inspection['correction_key'],
                'idempotency_key' => $inspection['correction_key'],
                'description' => $reason.' for '.$opening->document_number,
                'reversal_of_transaction_id' => $inspection['original_transaction_id'],
                'reason' => $reason,
            ]);

            $this->assertCorrection($transaction, $inspection, $targetAccount->id);

            if (! ($inspection['correction_exists'] ?? false)) {
                $this->auditTrailService->recordGeneric(
                    eventType: 'posting',
                    action: 'customer_control_account_reclassified',
                    auditable: $opening,
                    documentType: 'CUSTOMER_CONTROL_RECLASSIFICATION',
                    documentNo: $inspection['correction_document_number'],
                    userId: $actorId,
                    description: $reason,
                    metadata: [
                        'business_id' => $opening->business_id,
                        'original_document_number' => $opening->document_number,
                        'original_posting_transaction_id' => $inspection['original_transaction_id'],
                        'correction_posting_transaction_id' => $transaction->id,
                        'original_account_id' => $inspection['original_account_id'],
                        'corrected_account_id' => $targetAccount->id,
                        'amount' => $inspection['amount'],
                        'reason' => $reason,
                    ],
                );
            }

            return [...$inspection, 'reclassified' => ! ($inspection['correction_exists'] ?? false), 'idempotent' => (bool) ($inspection['correction_exists'] ?? false), 'correction_transaction_id' => $transaction->id];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(SubledgerOpeningBalance $opening, ChartOfAccount $targetAccount): array
    {
        if ($opening->party_type !== 'CUSTOMER' || ! $opening->customer_id || $opening->status !== SubledgerOpeningBalance::STATUS_POSTED) {
            throw new BusinessException('Only posted customer opening balances are eligible for this correction.');
        }

        $group = CustomerPostingGroup::query()->find($opening->customer_posting_group_id);
        if (! $group) {
            throw new BusinessException('The customer posting group for the opening balance could not be found.');
        }

        $originalAccountId = (int) $opening->control_account_id;
        if ((int) $group->receivables_account_id !== $originalAccountId
            && (int) $group->receivables_account_id !== (int) $targetAccount->id) {
            throw new BusinessException('The historical opening balance is no longer tied to the customer posting group account.');
        }

        $this->controlAccountAssignmentService->validateCustomerReceivables((int) $targetAccount->id);
        if ($targetAccount->id === $originalAccountId) {
            throw new BusinessException('The corrected receivables account must differ from the historical account.');
        }

        $setup = GeneralLedgerSetup::query()->first();
        if (! $setup || ! $setup->opening_balance_equity_account_id) {
            throw new BusinessException('Opening Balance Equity setup is required before reclassification.');
        }

        $originalTransaction = PostingTransaction::query()->find($opening->posting_transaction_id);
        if (! $originalTransaction || $originalTransaction->status !== 'completed') {
            throw new BusinessException('The completed original opening-balance transaction could not be verified.');
        }

        $amount = DecimalMath::currency($opening->amount_lcy);
        $lines = $originalTransaction->glEntries()->orderBy('id')->get();
        $matchesOriginal = $lines->count() === 2
            && $lines->contains(fn (GlEntry $line): bool => (int) $line->chart_of_account_id === $originalAccountId
                && DecimalMath::compare($line->debit_amount, $amount) === 0
                && DecimalMath::compare($line->credit_amount, '0') === 0)
            && $lines->contains(fn (GlEntry $line): bool => (int) $line->chart_of_account_id === (int) $setup->opening_balance_equity_account_id
                && DecimalMath::compare($line->debit_amount, '0') === 0
                && DecimalMath::compare($line->credit_amount, $amount) === 0);

        if (! $matchesOriginal) {
            throw new BusinessException('The historical opening-balance G/L lines do not match the known reclassification shape.');
        }

        $correctionKey = 'customer-control-reclass:'.$opening->document_number.':'.$originalAccountId.':'.$targetAccount->account_number;
        $correction = PostingTransaction::query()->where('idempotency_key', $correctionKey)->first();

        if ((int) $group->receivables_account_id !== $originalAccountId && ! $correction) {
            throw new BusinessException('The customer posting group already points to the corrected account but no approved correction transaction exists.');
        }

        return [
            'opening_balance_id' => $opening->id,
            'posting_group_id' => $group->id,
            'business_id' => $opening->business_id,
            'posting_date' => $opening->posting_date,
            'amount' => $amount,
            'original_document_number' => $opening->document_number,
            'original_account_id' => $originalAccountId,
            'target_account_id' => $targetAccount->id,
            'original_transaction_id' => $originalTransaction->id,
            'correction_document_number' => $opening->document_number.'-RCL',
            'correction_key' => $correctionKey,
            'correction_exists' => $correction !== null,
            'correction_transaction_id' => $correction?->id,
        ];
    }

    /** @param array<string, mixed> $inspection */
    private function assertCorrection(PostingTransaction $transaction, array $inspection, int $targetAccountId): void
    {
        if ((int) $transaction->reversal_of_transaction_id !== (int) $inspection['original_transaction_id']
            || $transaction->idempotency_key !== $inspection['correction_key']) {
            throw new BusinessException('The correction transaction metadata does not match the approved reclassification.');
        }

        $lines = $transaction->glEntries()->orderBy('id')->get();
        $amount = $inspection['amount'];
        $valid = $lines->count() === 2
            && $lines->contains(fn (GlEntry $line): bool => (int) $line->chart_of_account_id === $targetAccountId
                && DecimalMath::compare($line->debit_amount, $amount) === 0
                && DecimalMath::compare($line->credit_amount, '0') === 0)
            && $lines->contains(fn (GlEntry $line): bool => (int) $line->chart_of_account_id === (int) $inspection['original_account_id']
                && DecimalMath::compare($line->debit_amount, '0') === 0
                && DecimalMath::compare($line->credit_amount, $amount) === 0);

        if (! $valid) {
            throw new BusinessException('The existing or newly posted correction does not match the approved account reclassification.');
        }
    }
}
