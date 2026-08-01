<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLiabilityPostingStatus;
use App\Enums\CommissionPaymentMethod;
use App\Enums\CommissionSettlementBatchStatus;
use App\Models\CommissionLiabilityPosting;
use App\Models\CommissionSettlementBatch;
use App\Models\ReferralCommissionSetting;

class CommissionPaymentReadinessService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{code: string, severity: string, message: string}>
     */
    public function findings(CommissionSettlementBatch $batch, array $data = []): array
    {
        $findings = [];

        if ($batch->status !== CommissionSettlementBatchStatus::Locked) {
            $findings[] = $this->finding('settlement_not_locked', 'critical', 'Settlement batch must be locked before payment.');
        }

        if (! CommissionLiabilityPosting::query()->where('commission_settlement_batch_id', $batch->id)->where('status', CommissionLiabilityPostingStatus::Posted)->exists()) {
            $findings[] = $this->finding('liability_not_posted', 'critical', 'Commission liability must be posted before payment.');
        }

        $setting = ReferralCommissionSetting::query()->where('business_id', $batch->business_id)->first();
        if (! $setting?->commission_expense_account_id || ! $setting?->commission_payable_account_id) {
            $findings[] = $this->finding('posting_setup_missing', 'critical', 'Commission expense/payable accounts are not configured.');
        }

        $method = CommissionPaymentMethod::tryFrom((string) ($data['payment_method'] ?? CommissionPaymentMethod::BankTransfer->value));
        if (! in_array($method, [CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cash, CommissionPaymentMethod::Cheque], true)) {
            $findings[] = $this->finding('unsupported_payment_method', 'critical', 'Payment method is unsupported until explicit setup exists.');
        }

        if (in_array($method, [CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cheque], true) && blank($data['bank_account_id'] ?? null)) {
            $findings[] = $this->finding('bank_account_missing', 'critical', 'Bank account is required for bank transfer or cheque commission payment.');
        }

        if ($method === CommissionPaymentMethod::Cash && blank($data['cash_account_id'] ?? null)) {
            $findings[] = $this->finding('cash_account_missing', 'critical', 'Petty cash fund is required for cash commission payment.');
        }

        return $findings;
    }

    /**
     * @return array{code: string, severity: string, message: string}
     */
    private function finding(string $code, string $severity, string $message): array
    {
        return compact('code', 'severity', 'message');
    }
}
