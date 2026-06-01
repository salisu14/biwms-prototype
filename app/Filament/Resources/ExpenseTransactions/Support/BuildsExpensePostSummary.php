<?php

namespace App\Filament\Resources\ExpenseTransactions\Support;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;

trait BuildsExpensePostSummary
{
    private static function buildPostValidationSummary(object $record): string
    {
        $record->loadMissing(['expenseAccount', 'vendor', 'employee.employeePostingGroup', 'allocations']);

        $expenseAccount = $record->expenseAccount
            ? trim(($record->expenseAccount->account_number ?? '').' - '.($record->expenseAccount->name ?? ''))
            : 'Not set';

        $offsetAccount = self::resolveOffsetAccountLabel($record);

        $allocationPercentageTotal = (float) $record->allocations
            ->where('allocation_type', 'percentage')
            ->sum('allocation_percentage');

        $allocationAmountTotal = (float) $record->allocations
            ->where('allocation_type', 'amount')
            ->sum('allocated_amount');

        $allocationLineCount = $record->allocations->count();

        $allocationPercentageStatus = abs($allocationPercentageTotal - 100.0) < 0.01
            ? 'OK'
            : 'Warning';

        return implode("\n", [
            "Approval Status: {$record->status}",
            "Expense Account: {$expenseAccount}",
            "Offset Account: {$offsetAccount}",
            "Allocation Lines: {$allocationLineCount}",
            'Allocation % Total: '.number_format($allocationPercentageTotal, 2)."% ({$allocationPercentageStatus})",
            'Allocation Amount Total: '.number_format($allocationAmountTotal, 2),
        ]);
    }

    private static function resolveOffsetAccountLabel(object $record): string
    {
        if ($record->vendor && method_exists($record->vendor, 'getPayablesAccount')) {
            $vendorPayables = $record->vendor->getPayablesAccount();
            if ($vendorPayables) {
                return trim(($vendorPayables->account_number ?? '').' - '.($vendorPayables->name ?? ''));
            }
        }

        if ($record->employee?->employeePostingGroup?->payables_account_id) {
            $employeePayables = ChartOfAccount::find($record->employee->employeePostingGroup->payables_account_id);
            if ($employeePayables) {
                return trim(($employeePayables->account_number ?? '').' - '.($employeePayables->name ?? ''));
            }
        }

        $defaultOffsetAccountId = GeneralLedgerSetup::instance()->default_expense_offset_account_id;
        if ($defaultOffsetAccountId) {
            $defaultOffset = ChartOfAccount::find($defaultOffsetAccountId);
            if ($defaultOffset) {
                return trim(($defaultOffset->account_number ?? '').' - '.($defaultOffset->name ?? ''));
            }
        }

        return 'Not configured';
    }
}
