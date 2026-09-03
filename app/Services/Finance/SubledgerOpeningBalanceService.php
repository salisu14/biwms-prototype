<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GeneralLedgerSetup;
use App\Models\SubledgerOpeningBalance;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\AuditTrailService;
use App\Services\Business\BusinessContextService;
use App\Services\NumberSeriesService;
use App\Services\PostingDateValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SubledgerOpeningBalanceService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly BusinessContextService $businessContext,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(array $data, ?int $actorId = null): SubledgerOpeningBalance
    {
        $actorId ??= auth()->id();
        if (! $actorId) {
            throw new BusinessException('Authentication is required to create an opening balance.');
        }
        Gate::forUser(User::query()->findOrFail($actorId))->authorize('create', SubledgerOpeningBalance::class);

        $partyType = strtoupper((string) ($data['party_type'] ?? ''));
        if (! in_array($partyType, ['CUSTOMER', 'VENDOR'], true)) {
            throw new BusinessException('Opening balance party type must be Customer or Vendor.');
        }

        Gate::forUser(User::query()->findOrFail($actorId))->authorize(
            $partyType === 'CUSTOMER' ? 'createCustomer' : 'createVendor',
            SubledgerOpeningBalance::class,
        );

        $party = $this->resolveParty($partyType, (int) ($data['party_id'] ?? 0));
        $requestedBusinessId = isset($data['business_id']) ? (int) $data['business_id'] : null;
        $activeBusinessId = $this->businessContext->resolveId();
        if ($requestedBusinessId !== null && $activeBusinessId !== null && $requestedBusinessId !== $activeBusinessId) {
            throw new BusinessException('The opening balance business must match the active business.');
        }
        $businessId = $this->businessContext->resolveId($requestedBusinessId);
        if ($businessId <= 0) {
            throw new BusinessException('An active business is required for an opening balance.');
        }

        $amount = (float) ($data['original_amount'] ?? 0);
        if ($amount <= 0) {
            throw new BusinessException('Opening balance amount must be greater than zero.');
        }

        $postingDate = $data['posting_date'] ?? now();
        $currencyCode = strtoupper((string) ($data['currency_code'] ?? 'NGN'));
        $currencyFactor = (float) ($data['currency_factor'] ?? 1);
        if ($currencyFactor <= 0) {
            throw new BusinessException('Currency factor must be greater than zero.');
        }

        $group = $partyType === 'CUSTOMER' ? $party->customerPostingGroup : $party->vendorPostingGroup;
        $controlAccount = $partyType === 'CUSTOMER'
            ? $group?->receivablesAccount
            : $group?->payablesAccount;
        if (! $controlAccount) {
            throw new BusinessException('The party posting group does not have a configured control account.');
        }

        $numberSeries = $partyType === 'CUSTOMER' ? 'CUSTOMER-OPENING' : 'VENDOR-OPENING';

        return DB::transaction(function () use ($actorId, $businessId, $partyType, $party, $data, $amount, $postingDate, $currencyCode, $currencyFactor, $controlAccount, $numberSeries): SubledgerOpeningBalance {
            $partyModel = $partyType === 'CUSTOMER' ? Customer::class : Vendor::class;
            $partyIdColumn = $partyType === 'CUSTOMER' ? 'customer_id' : 'vendor_id';
            $lockedParty = $partyModel::query()->lockForUpdate()->findOrFail($party->id);

            $existing = SubledgerOpeningBalance::query()
                ->where('business_id', $businessId)
                ->where('party_type', $partyType)
                ->where($partyIdColumn, $lockedParty->id)
                ->whereIn('status', [SubledgerOpeningBalance::STATUS_DRAFT, SubledgerOpeningBalance::STATUS_POSTED])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new BusinessException("An active {$partyType} opening balance already exists ({$existing->document_number}). Open that document instead.");
            }

            $documentNumber = $this->numberSeriesService->getNextNo($numberSeries, Carbon::parse($postingDate));
            $equity = $this->openingEquityAccount();
            $amountLcy = round($amount * $currencyFactor, 4);

            $opening = SubledgerOpeningBalance::query()->create([
                'business_id' => $businessId,
                'party_type' => $partyType,
                'customer_id' => $partyType === 'CUSTOMER' ? $party->id : null,
                'vendor_id' => $partyType === 'VENDOR' ? $party->id : null,
                'document_number' => $documentNumber,
                'external_document_number' => $data['external_document_number'] ?? null,
                'original_document_type' => $data['original_document_type'] ?? 'OPENING_BALANCE',
                'posting_date' => $postingDate,
                'document_date' => $data['document_date'] ?? $postingDate,
                'due_date' => $data['due_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'currency_code' => $currencyCode,
                'original_amount' => $amount,
                'currency_factor' => $currencyFactor,
                'amount_lcy' => $amountLcy,
                'remaining_amount' => $amount,
                'remaining_amount_lcy' => $amountLcy,
                'control_account_id' => $controlAccount->id,
                'opening_equity_account_id' => $equity->id,
                'general_business_posting_group_id' => $party->general_business_posting_group_id,
                'customer_posting_group_id' => $partyType === 'CUSTOMER' ? $party->customer_posting_group_id : null,
                'vendor_posting_group_id' => $partyType === 'VENDOR' ? $party->vendor_posting_group_id : null,
                'description' => $data['description'] ?? 'Opening balance',
                'source_type' => self::class,
                'dimensions' => $data['dimensions'] ?? [],
                'status' => SubledgerOpeningBalance::STATUS_DRAFT,
                'created_by' => $actorId,
                'idempotency_key' => 'SUBLEDGER-OPENING:'.$businessId.':'.$partyType.':'.$party->id.':'.$documentNumber,
            ]);
            $opening->forceFill(['source_id' => $opening->id])->save();

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'opening_balance',
                action: 'subledger_opening_balance_created',
                auditable: $opening,
                documentType: 'SUBLEDGER_OPENING',
                documentNo: $opening->document_number,
                userId: $actorId,
                description: "Created {$partyType} opening balance {$opening->document_number}",
                metadata: ['business_id' => $businessId, 'party_type' => $partyType, 'party_id' => $party->id],
            );

            return $opening->fresh(['customer', 'vendor', 'controlAccount', 'openingEquityAccount']);
        });
    }

    public function post(SubledgerOpeningBalance $opening, ?int $actorId = null): SubledgerOpeningBalance
    {
        $actorId ??= auth()->id();
        if (! $actorId) {
            throw new BusinessException('Authentication is required to post an opening balance.');
        }
        Gate::forUser(User::query()->findOrFail($actorId))->authorize('post', $opening);

        return DB::transaction(function () use ($opening, $actorId): SubledgerOpeningBalance {
            $opening = SubledgerOpeningBalance::query()->lockForUpdate()->findOrFail($opening->id);
            if ($opening->status === SubledgerOpeningBalance::STATUS_POSTED) {
                return $opening;
            }
            if ($opening->status !== SubledgerOpeningBalance::STATUS_DRAFT) {
                throw new BusinessException('Only draft opening balances can be posted.');
            }

            $this->postingDateValidator->validate($opening->posting_date);
            $equity = ChartOfAccount::query()->find($opening->opening_equity_account_id);
            $control = ChartOfAccount::query()->find($opening->control_account_id);
            if (! $equity?->allowsDirectPosting() || ! $control?->allowsDirectPosting()) {
                throw new BusinessException('Opening balance accounts must be active direct-posting accounts.');
            }
            if ($equity->isSystemControlled() || in_array($equity->id, [$control->id], true)) {
                throw new BusinessException('The opening balance clearing account is incompatible with the control account.');
            }

            $party = $opening->party_type === 'CUSTOMER'
                ? Customer::query()->lockForUpdate()->findOrFail($opening->customer_id)
                : Vendor::query()->lockForUpdate()->findOrFail($opening->vendor_id);
            $currentGroup = $opening->party_type === 'CUSTOMER' ? $party->customerPostingGroup : $party->vendorPostingGroup;
            $currentControl = $opening->party_type === 'CUSTOMER'
                ? $currentGroup?->receivablesAccount
                : $currentGroup?->payablesAccount;
            if (! $currentControl || (int) $currentControl->id !== (int) $opening->control_account_id) {
                throw new BusinessException('The opening balance control account no longer matches the party posting group.');
            }
            $existing = $opening->party_type === 'CUSTOMER'
                ? CustomerLedgerEntry::query()->where('source_type', SubledgerOpeningBalance::class)->where('source_id', $opening->id)->first()
                : VendorLedgerEntry::query()->where('source_type', SubledgerOpeningBalance::class)->where('source_id', $opening->id)->first();
            if ($existing) {
                throw new BusinessException('This opening balance has already produced a ledger entry.');
            }

            $amount = (float) $opening->amount_lcy;
            $sourceType = $opening->party_type === 'CUSTOMER' ? SourceType::CUSTOMER->value : SourceType::VENDOR->value;
            $isCustomer = $opening->party_type === 'CUSTOMER';
            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $isCustomer ? $control->id : $equity->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'source_type' => $sourceType,
                    'description' => $opening->description,
                ],
                [
                    'account_id' => $isCustomer ? $equity->id : $control->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'source_type' => $sourceType,
                    'description' => $opening->description,
                ],
            ], [
                'business_id' => $opening->business_id,
                'source_module' => 'finance',
                'source_type' => $sourceType,
                'source_id' => $opening->id,
                'source_number' => $opening->document_number,
                'document_type' => 'SUBLEDGER_OPENING',
                'document_number' => $opening->document_number,
                'posting_date' => $opening->posting_date,
                'document_date' => $opening->document_date,
                'currency_code' => $opening->currency_code,
                'exchange_rate' => $opening->currency_factor,
                'dimensions' => $opening->dimensions ?? [],
                'actor_id' => $actorId,
                'idempotency_key' => $opening->idempotency_key,
                'transaction_key' => $opening->idempotency_key,
                'description' => $opening->description,
            ]);

            $ledgerData = [
                'entry_number' => $this->nextLedgerNumber($opening),
                'business_id' => $opening->business_id,
                'document_type' => 'OPENING_BALANCE',
                'document_number' => $opening->document_number,
                'description' => $opening->description,
                'posting_date' => $opening->posting_date,
                'document_date' => $opening->document_date,
                'due_date' => $opening->due_date,
                'amount' => $opening->party_type === 'CUSTOMER' ? $amount : -$amount,
                'debit_amount' => $opening->party_type === 'CUSTOMER' ? $amount : 0,
                'credit_amount' => $opening->party_type === 'CUSTOMER' ? 0 : $amount,
                'running_balance' => $opening->party_type === 'CUSTOMER'
                    ? $this->partyBalance($opening, $amount)
                    : $this->partyBalance($opening, -$amount),
                // Ledger remaining amounts are maintained in local currency;
                // original_amount remains the document-currency snapshot.
                'remaining_amount' => $amount,
                'open' => true,
                'fully_applied' => false,
                'currency_id' => $opening->currency_id,
                'currency_code' => $opening->currency_code,
                'original_debit_amount' => $opening->party_type === 'CUSTOMER' ? $opening->original_amount : 0,
                'original_credit_amount' => $opening->party_type === 'CUSTOMER' ? 0 : $opening->original_amount,
                'currency_factor' => $opening->currency_factor,
                'general_business_posting_group_id' => $opening->general_business_posting_group_id,
                'customer_posting_group_id' => $opening->customer_posting_group_id,
                'vendor_posting_group_id' => $opening->vendor_posting_group_id,
                'gl_entry_id' => $transaction->glEntries->firstWhere('chart_of_account_id', $control->id)?->id,
                'source_id' => $opening->id,
                'source_type' => SubledgerOpeningBalance::class,
                'created_by' => $actorId,
                'dimensions' => $opening->dimensions ?? [],
            ];
            $ledger = $opening->party_type === 'CUSTOMER'
                ? CustomerLedgerEntry::query()->create(['customer_id' => $party->id, ...$ledgerData])
                : VendorLedgerEntry::query()->create(['vendor_id' => $party->id, ...$ledgerData]);

            $controlGlEntry = $transaction->glEntries->firstWhere('chart_of_account_id', $control->id);
            if ($controlGlEntry) {
                $controlGlEntry->forceFill($opening->party_type === 'CUSTOMER'
                    ? ['cust_ledger_entry_id' => $ledger->id]
                    : ['vendor_ledger_entry_id' => $ledger->id])->saveQuietly();
            }

            SubledgerOpeningBalance::allowServiceTransition(fn (): bool => $opening->update([
                'status' => SubledgerOpeningBalance::STATUS_POSTED,
                'posted_by' => $actorId,
                'posted_at' => now(),
                'posting_transaction_id' => $transaction->id,
                'customer_ledger_entry_id' => $opening->party_type === 'CUSTOMER' ? $ledger->id : null,
                'vendor_ledger_entry_id' => $opening->party_type === 'VENDOR' ? $ledger->id : null,
            ]));

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'opening_balance',
                action: 'subledger_opening_balance_posted',
                auditable: $opening,
                documentType: 'SUBLEDGER_OPENING',
                documentNo: $opening->document_number,
                userId: $actorId,
                description: "Posted opening balance {$opening->document_number}",
                metadata: ['business_id' => $opening->business_id, 'posting_transaction_id' => $transaction->id, 'ledger_entry_id' => $ledger->id],
            );

            return $opening->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(SubledgerOpeningBalance $opening, array $data): SubledgerOpeningBalance
    {
        return DB::transaction(function () use ($opening, $data): SubledgerOpeningBalance {
            $locked = SubledgerOpeningBalance::query()->lockForUpdate()->findOrFail($opening->id);
            if ($locked->status !== SubledgerOpeningBalance::STATUS_DRAFT) {
                throw new BusinessException('Only draft opening balances can be edited.');
            }

            $partyType = strtoupper((string) ($data['party_type'] ?? $locked->party_type));
            $party = $this->resolveParty($partyType, (int) ($data['party_id'] ?? 0));
            $group = $partyType === 'CUSTOMER' ? $party->customerPostingGroup : $party->vendorPostingGroup;
            $control = $partyType === 'CUSTOMER' ? $group?->receivablesAccount : $group?->payablesAccount;
            if (! $control) {
                throw new BusinessException('The party posting group does not have a configured control account.');
            }

            $amount = (float) ($data['original_amount'] ?? $locked->original_amount);
            $factor = (float) ($data['currency_factor'] ?? $locked->currency_factor);
            if ($amount <= 0 || $factor <= 0) {
                throw new BusinessException('Opening amount and currency factor must be greater than zero.');
            }

            $locked->forceFill([
                'party_type' => $partyType,
                'customer_id' => $partyType === 'CUSTOMER' ? $party->id : null,
                'vendor_id' => $partyType === 'VENDOR' ? $party->id : null,
                'original_amount' => $amount,
                'currency_code' => strtoupper((string) ($data['currency_code'] ?? $locked->currency_code)),
                'currency_factor' => $factor,
                'amount_lcy' => round($amount * $factor, 4),
                'remaining_amount' => $amount,
                'remaining_amount_lcy' => round($amount * $factor, 4),
                'posting_date' => $data['posting_date'] ?? $locked->posting_date,
                'document_date' => $data['document_date'] ?? $locked->document_date,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? $locked->description,
                'external_document_number' => $data['external_document_number'] ?? null,
                'control_account_id' => $control->id,
                'general_business_posting_group_id' => $party->general_business_posting_group_id,
                'customer_posting_group_id' => $partyType === 'CUSTOMER' ? $party->customer_posting_group_id : null,
                'vendor_posting_group_id' => $partyType === 'VENDOR' ? $party->vendor_posting_group_id : null,
            ])->save();

            return $locked->fresh();
        });
    }

    public function reverse(SubledgerOpeningBalance $opening, string $reason, ?int $actorId = null): SubledgerOpeningBalance
    {
        $actorId ??= auth()->id();
        Gate::forUser(User::query()->findOrFail($actorId))->authorize('reverse', $opening);

        return DB::transaction(function () use ($opening, $reason, $actorId): SubledgerOpeningBalance {
            $opening = SubledgerOpeningBalance::query()->lockForUpdate()->findOrFail($opening->id);
            if ($opening->status !== SubledgerOpeningBalance::STATUS_POSTED) {
                throw new BusinessException('Only posted opening balances can be reversed.');
            }
            $this->postingDateValidator->validate(now());

            $ledger = $opening->party_type === 'CUSTOMER'
                ? $opening->customerLedgerEntry()->lockForUpdate()->firstOrFail()
                : $opening->vendorLedgerEntry()->lockForUpdate()->firstOrFail();
            if (filled($ledger->applied_to_entries)) {
                throw new BusinessException('A settled opening balance must be unapplied before reversal.');
            }

            $ledger->reverse($actorId, $reason);
            SubledgerOpeningBalance::allowServiceTransition(fn (): bool => $opening->update([
                'status' => SubledgerOpeningBalance::STATUS_REVERSED,
                'reversed_by' => $actorId,
                'reversed_at' => now(),
            ]));

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'opening_balance',
                action: 'subledger_opening_balance_reversed',
                auditable: $opening,
                documentType: 'SUBLEDGER_OPENING',
                documentNo: $opening->document_number,
                userId: $actorId,
                description: "Reversed opening balance {$opening->document_number}",
                metadata: ['business_id' => $opening->business_id, 'reason' => $reason],
            );

            return $opening->fresh();
        });
    }

    private function resolveParty(string $partyType, int $partyId): Customer|Vendor
    {
        if ($partyType === 'CUSTOMER') {
            return Customer::query()->with(['customerPostingGroup', 'generalBusinessPostingGroup'])->findOrFail($partyId);
        }
        if ($partyType === 'VENDOR') {
            return Vendor::query()->with(['vendorPostingGroup', 'generalBusinessPostingGroup'])->findOrFail($partyId);
        }

        throw new BusinessException('Opening balance party type must be CUSTOMER or VENDOR.');
    }

    private function openingEquityAccount(): ChartOfAccount
    {
        $account = GeneralLedgerSetup::instance()->openingBalanceEquityAccount;
        if (! $account || ! $account->allowsDirectPosting() || $account->isSystemControlled()) {
            throw new BusinessException('Opening balance equity/clearing account is not configured for direct posting.');
        }

        return $account;
    }

    private function nextLedgerNumber(SubledgerOpeningBalance $opening): int
    {
        return $opening->party_type === 'CUSTOMER'
            ? ((int) CustomerLedgerEntry::query()->where('customer_id', $opening->customer_id)->max('entry_number')) + 1
            : ((int) VendorLedgerEntry::query()->where('vendor_id', $opening->vendor_id)->max('entry_number')) + 1;
    }

    private function partyBalance(SubledgerOpeningBalance $opening, float $amount): float
    {
        $query = $opening->party_type === 'CUSTOMER'
            ? CustomerLedgerEntry::query()->where('customer_id', $opening->customer_id)
            : VendorLedgerEntry::query()->where('vendor_id', $opening->vendor_id);

        return (float) $query->sum('amount') + $amount;
    }
}
