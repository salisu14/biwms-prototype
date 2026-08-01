<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Models\ReferralCommissionSetting;
use RuntimeException;

class CommissionPostingSetupResolver
{
    /**
     * @return array{expense_account_id: int, payable_account_id: int, rounding_account_id: int|null, clearing_account_id: int|null}
     */
    public function liabilityAccounts(?int $businessId): array
    {
        $setting = ReferralCommissionSetting::query()
            ->where('business_id', $businessId)
            ->first();

        if (! $setting) {
            throw new RuntimeException('Referral commission settings are required before commission liability posting.');
        }

        if (! $setting->commission_expense_account_id || ! $setting->commission_payable_account_id) {
            throw new RuntimeException('Commission expense and payable accounts must be configured before commission liability posting.');
        }

        return [
            'expense_account_id' => (int) $setting->commission_expense_account_id,
            'payable_account_id' => (int) $setting->commission_payable_account_id,
            'rounding_account_id' => $setting->commission_rounding_account_id ? (int) $setting->commission_rounding_account_id : null,
            'clearing_account_id' => $setting->commission_payment_clearing_account_id ? (int) $setting->commission_payment_clearing_account_id : null,
        ];
    }
}
