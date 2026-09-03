<?php

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Models\ChartOfAccount;
use Illuminate\Validation\ValidationException;

class ControlAccountAssignmentService
{
    public function validateCustomerReceivables(?int $accountId): void
    {
        $this->validate(
            $accountId,
            [AccountCategory::ASSET, AccountCategory::LIQUID_ASSET, AccountCategory::RECEIVABLE],
            'receivables_account_id',
            'The selected Receivables Account must be a Balance Sheet asset/receivables account and cannot be an Income Statement revenue or expense account.'
        );
    }

    public function validateVendorPayables(?int $accountId): void
    {
        $this->validate(
            $accountId,
            [AccountCategory::LIABILITY, AccountCategory::PAYABLE],
            'payables_account_id',
            'The selected Payables Account must be a Balance Sheet liability/payables account and cannot be an Income Statement revenue or expense account.'
        );
    }

    /**
     * @param  list<AccountCategory>  $allowedCategories
     */
    private function validate(?int $accountId, array $allowedCategories, string $field, string $message): void
    {
        $account = $accountId === null ? null : ChartOfAccount::query()->find($accountId);

        $category = $account?->account_category;
        $category = $category instanceof AccountCategory ? $category : AccountCategory::tryFrom((string) $category);

        if (
            ! $account
            || ! $account->isPostingAccount()
            || ! $account->direct_posting
            || $account->blocked
            || ! $category
            || ! in_array($category, $allowedCategories, true)
            || ! $account->income_balance?->isBalanceSheet()
        ) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
