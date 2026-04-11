<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\COGSCategory;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\RevenueCategory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\ExpenseBudget;
use App\Models\ExpenseCategory;
use App\Models\ExpenseTransaction;
use App\Models\Item;
use App\Models\Vendor;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly GeneralLedgerService $glService,
        private readonly CurrencyService $currencyService,
        private readonly PostingService $postingService
    ) {}

    /**
     * Post COGS from inventory transaction
     */
    public function postCOGS(
        Item $item,
        float $quantity,
        float $unitCost,
        string $documentNo,
        ?Category $category = null
    ): ExpenseTransaction {
        $amount = $quantity * $unitCost;

        return DB::transaction(function () use ($item, $quantity, $amount, $documentNo, $category) {
            // Determine COGS account from posting setup
            $cogsAccount = $this->resolveCOGSAccount($item, $category);

            $transaction = ExpenseTransaction::create([
                'document_type' => 'inventory',
                'document_no' => $documentNo,
                'posting_date' => now(),
                'account_type' => AccountType::COGS,
                'category_code' => COGSCategory::MATERIALS->value,
                'expense_type' => 'direct',
                'amount' => $amount,
                'amount_lcy' => $amount,
                'item_id' => $item->id,
                'category_id' => $category?->id ?? $item->getPrimaryCategory()?->id,
                'expense_account_id' => $cogsAccount->id,
                'posted_by' => Auth::id(),
                'description' => "COGS for {$item->item_no} x {$quantity}",
            ]);

            // Post to GL
            $this->postToGL($transaction);

            return $transaction;
        });
    }

    /**
     * Post Sales Return (Contra-Revenue)
     */
    public function postSalesReturn(
        Customer $customer,
        Item $item,
        float $quantity,
        float $unitPrice,
        ?Category $category = null,
        ?string $originalInvoiceNo = null
    ): ExpenseTransaction {
        $amount = $quantity * $unitPrice;

        return DB::transaction(function () use ($customer, $item, $quantity, $amount, $category, $originalInvoiceNo) {
            $expenseCategory = ExpenseCategory::where('category_code', RevenueCategory::SALES_RETURN->value)->first();

            $transaction = ExpenseTransaction::create([
                'document_type' => 'credit_memo',
                'document_no' => $this->numberSeriesService->getNextNo('S-RETURN'),
                'posting_date' => now(),
                'account_type' => AccountType::REVENUE,
                'category_code' => RevenueCategory::SALES_RETURN->value,
                'amount' => -$amount, // Negative revenue
                'amount_lcy' => -$amount,
                'customer_id' => $customer->id,
                'item_id' => $item->id,
                'category_id' => $category?->id ?? $item->getPrimaryCategory()?->id,
                'invoice_no' => $originalInvoiceNo,
                'expense_account_id' => $expenseCategory?->expense_account_id,
                'posted_by' => Auth::id(),
                'description' => "Sales return for {$item->item_no} x {$quantity}",
            ]);

            // Post to GL (debit revenue)
            $this->postToGL($transaction);

            // Also post COGS reversal
            $this->reverseCOGS($item, $quantity, $transaction->document_no);

            return $transaction;
        });
    }

    /**
     * Post Sales Discount (Contra-Revenue or Financial Charge)
     */
    public function postSalesDiscount(
        Customer $customer,
        float $discountAmount,
        string $baseDocumentNo,
        ?Category $category = null,
        bool $isEarlyPaymentDiscount = false
    ): ExpenseTransaction {
        $expenseCategory = $isEarlyPaymentDiscount
            ? ExpenseCategory::where('category_code', 'early_payment_discount')->first()
            : ExpenseCategory::where('category_code', RevenueCategory::SALES_DISCOUNT->value)->first();

        return ExpenseTransaction::create([
            'document_type' => 'discount',
            'document_no' => $this->numberSeriesService->getNextNo('S-DISC'),
            'posting_date' => now(),
            'account_type' => $isEarlyPaymentDiscount ? AccountType::INDIRECT_EXPENSE : AccountType::REVENUE,
            'category_code' => $isEarlyPaymentDiscount ? 'early_payment_discount' : RevenueCategory::SALES_DISCOUNT->value,
            'amount' => -$discountAmount,
            'amount_lcy' => -$discountAmount,
            'customer_id' => $customer->id,
            'category_id' => $category?->id,
            'invoice_no' => $baseDocumentNo,
            'expense_account_id' => $expenseCategory?->expense_account_id,
            'posted_by' => Auth::id(),
            'description' => $isEarlyPaymentDiscount ? 'Early payment discount' : 'Sales discount',
        ]);
    }

    /**
     * Post Inventory Adjustment
     */
    public function postInventoryAdjustment(
        Item $item,
        float $quantity,
        float $unitCost,
        string $reasonCode,
        bool $isPositive = false
    ): ExpenseTransaction {
        $amount = abs($quantity * $unitCost);
        $category = match ($reasonCode) {
            'write_off' => ExpenseCategoryEnum::INVENTORY_WRITE_OFF,
            'damage' => ExpenseCategoryEnum::INVENTORY_ADJUSTMENT,
            'count' => ExpenseCategoryEnum::INVENTORY_ADJUSTMENT,
            default => ExpenseCategoryEnum::INVENTORY_ADJUSTMENT,
        };

        return ExpenseTransaction::create([
            'document_type' => 'adjustment',
            'document_no' => $this->numberSeriesService->getNextNo('ADJ'),
            'posting_date' => now(),
            'account_type' => AccountType::COGS,
            'category_code' => $category->value,
            'expense_type' => 'indirect',
            'amount' => $isPositive ? -$amount : $amount,
            'amount_lcy' => $isPositive ? -$amount : $amount,
            'item_id' => $item->id,
            'category_id' => $item->getPrimaryCategory()?->id,
            'expense_account_id' => $this->resolveAdjustmentAccount($category)->id,
            'posted_by' => Auth::id(),
            'description' => "Inventory adjustment: {$reasonCode} for {$item->item_no}",
        ]);
    }

    /**
     * Post Depreciation Expense
     */
    public function postDepreciationExpense(
        Asset $asset,
        float $amount,
        \DateTime $postingDate
    ): ExpenseTransaction {
        $category = $asset->isPlantMachinery()
            ? ExpenseCategoryEnum::DEPRECIATION_PLANT
            : ExpenseCategoryEnum::DEPRECIATION_PLANT; // Extend for other types

        return ExpenseTransaction::create([
            'document_type' => 'depreciation',
            'document_no' => $this->numberSeriesService->getNextNo('DEPR'),
            'posting_date' => $postingDate,
            'account_type' => AccountType::INDIRECT_EXPENSE,
            'category_code' => $category->value,
            'expense_type' => 'indirect',
            'amount' => $amount,
            'amount_lcy' => $amount,
            'expense_account_id' => $asset->depreciation_expense_account_id,
            'shortcut_dimension_1_code' => $asset->shortcut_dimension_1_code,
            'posted_by' => Auth::id(),
            'description' => "Depreciation of {$asset->asset_no}",
        ]);
    }

    /**
     * Post Purchase Account (Interim - BC Pattern)
     */
    public function postPurchaseInterim(
        Vendor $vendor,
        float $amount,
        string $documentNo,
        ?string $currencyCode = null
    ): ExpenseTransaction {
        $category = COGSCategory::PURCHASE_ACCOUNT;
        $interimAccount = ChartOfAccount::where('account_code', '2100')->first(); // Purchase interim

        return ExpenseTransaction::create([
            'document_type' => 'purchase_order',
            'document_no' => $documentNo,
            'posting_date' => now(),
            'account_type' => AccountType::COGS,
            'category_code' => $category->value,
            'expense_type' => 'direct',
            'amount' => $amount,
            'amount_lcy' => $this->currencyService->toLCY($amount, $currencyCode),
            'currency_code' => $currencyCode,
            'vendor_id' => $vendor->id,
            'category_id' => null, // Or resolve if needed
            'expense_account_id' => $interimAccount->id,
            'posted_by' => Auth::id(),
            'description' => "Purchase interim for PO {$documentNo}",
        ]);
    }

    /**
     * Allocate indirect expense to cost centers
     */
    public function allocateIndirectExpense(
        ExpenseTransaction $expense,
        array $allocations // [['department' => 'PROD', 'percentage' => 60], ...]
    ): void {
        if ($expense->isDirect()) {
            throw new \InvalidArgumentException('Only indirect expenses can be allocated');
        }

        DB::transaction(function () use ($expense, $allocations) {
            foreach ($allocations as $alloc) {
                $allocatedAmount = $expense->amount * ($alloc['percentage'] / 100);

                $expense->allocations()->create([
                    'allocation_basis' => $alloc['basis'] ?? 'manual',
                    'allocation_percentage' => $alloc['percentage'],
                    'allocated_amount' => $allocatedAmount,
                    'target_dimension_1' => $alloc['department'],
                    'target_dimension_2' => $alloc['project'] ?? null,
                    'target_gl_account_id' => $expense->expense_account_id,
                ]);

                // Post allocation to GL
                $this->postAllocationToGL($expense, $alloc, $allocatedAmount);
            }
        });
    }

    /**
     * Get expense report by category
     */
    public function getExpenseReport(
        \DateTime $from,
        \DateTime $to,
        ?string $dimension1 = null,
        ?string $dimension2 = null
    ): array {
        $query = ExpenseTransaction::byPeriod($from, $to)
            ->whereIn('account_type', [AccountType::COGS, AccountType::DIRECT_EXPENSE, AccountType::INDIRECT_EXPENSE]);

        if ($dimension1) {
            $query->where('shortcut_dimension_1_code', $dimension1);
        }
        if ($dimension2) {
            $query->where('shortcut_dimension_2_code', $dimension2);
        }

        $transactions = $query->get();

        return [
            'summary' => [
                'total_cogs' => $transactions->where('account_type', AccountType::COGS)->sum('amount'),
                'total_direct' => $transactions->where('expense_type', 'direct')->sum('amount'),
                'total_indirect' => $transactions->where('expense_type', 'indirect')->sum('amount'),
            ],
            'by_category' => $transactions->groupBy('category_code')->map(function ($group) {
                return [
                    'category' => $group->first()->category_code,
                    'total' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            }),
            'by_department' => $transactions->groupBy('shortcut_dimension_1_code')->map(function ($group) {
                return [
                    'department' => $group->first()->shortcut_dimension_1_code,
                    'total' => $group->sum('amount'),
                ];
            }),
        ];
    }

    /**
     * Budget vs Actual analysis
     */
    public function getBudgetVariance(int $fiscalYear, int $month): array
    {
        $budgets = ExpenseBudget::where('fiscal_year', $fiscalYear)->get();

        $actuals = ExpenseTransaction::whereYear('posting_date', $fiscalYear)
            ->whereMonth('posting_date', $month)
            ->get()
            ->groupBy('category_code');

        return $budgets->map(function ($budget) use ($actuals, $month) {
            $actual = $actuals->get($budget->category_code, collect())->sum('amount');
            $budgeted = $budget->getMonthValue($month);

            return [
                'category' => $budget->category_code,
                'budgeted' => $budgeted,
                'actual' => $actual,
                'variance' => $budgeted - $actual,
                'variance_percent' => $budgeted > 0 ? (($budgeted - $actual) / $budgeted) * 100 : 0,
            ];
        })->toArray();
    }

    /**
     * Get variance for a specific category
     */
    public function getCategoryBudgetVariance(ExpenseCategory $category, int $fiscalYear, int $month): array
    {
        $budget = ExpenseBudget::where('category_code', $category->category_code)
            ->where('fiscal_year', $fiscalYear)
            ->first();

        $actual = ExpenseTransaction::where('category_code', $category->category_code)
            ->whereYear('posting_date', $fiscalYear)
            ->whereMonth('posting_date', $month)
            ->sum('amount');

        $budgeted = $budget ? $budget->getMonthValue($month) : 0;

        return [
            'category' => $category->category_code,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'variance' => $budgeted - $actual,
            'variance_percent' => $budgeted > 0 ? (($budgeted - $actual) / $budgeted) * 100 : 0,
        ];
    }

    // Private methods
    private function resolveCOGSAccount(Item $item, ?Category $category = null): ChartOfAccount
    {
        // Priority: Item posting setup > Product category > Default
        return $item->inventoryPostingSetup?->cogs_account
            ?? $category?->cogs_account // This might be null if Category doesn't have cogs_account. Let's check Category model.
            ?? ChartOfAccount::where('account_code', '5100')->first();
    }

    private function resolveAdjustmentAccount(ExpenseCategory $category): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', '5150')->first(); // Inventory adjustments
    }

    private function postToGL(ExpenseTransaction $transaction): void
    {
        // Debit expense/COGS account
        $this->postingService->createGlEntry([
            'chart_of_account_id' => $transaction->expense_account_id,
            'posting_date' => $transaction->posting_date,
            'document_type' => $transaction->document_type,
            'document_number' => $transaction->document_no,
            'description' => $transaction->description,
            'debit_amount' => $transaction->amount > 0 ? (float) $transaction->amount : 0,
            'credit_amount' => $transaction->amount < 0 ? (float) abs($transaction->amount) : 0,
            'shortcut_dimension_1_code' => $transaction->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $transaction->shortcut_dimension_2_code,
        ]);
    }

    private function reverseCOGS(Item $item, float $quantity, string $documentNo): void
    {
        $amount = $quantity * ($item->unit_cost ?? 0);
        $cogsAccount = $this->resolveCOGSAccount($item);
        $inventoryAccount = $item->getInventoryAccount();

        if ($amount > 0 && $cogsAccount && $inventoryAccount) {
            // 1. Debit Inventory (Increase)
            $this->postingService->createGlEntry([
                'chart_of_account_id' => $inventoryAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'source_type' => 'ITEM',
                'source_number' => $item->item_code,
                'document_type' => 'SALES_RETURN',
                'document_number' => $documentNo,
                'posting_date' => now(),
                'description' => "Reverse COGS (Inv): {$item->item_code}",
            ]);

            // 2. Credit COGS (Decrease)
            $this->postingService->createGlEntry([
                'chart_of_account_id' => $cogsAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'source_type' => 'ITEM',
                'source_number' => $item->item_code,
                'document_type' => 'SALES_RETURN',
                'document_number' => $documentNo,
                'posting_date' => now(),
                'description' => "Reverse COGS (Exp): {$item->item_code}",
            ]);
        }
    }

    private function postAllocationToGL(ExpenseTransaction $expense, array $alloc, float $amount): void
    {
        // Credit source expense account
        $this->postingService->createGlEntry([
            'chart_of_account_id' => $expense->expense_account_id,
            'posting_date' => now(),
            'document_type' => 'ALLOCATION',
            'document_number' => $expense->document_no,
            'description' => "Allocation OUT from {$expense->document_no}",
            'debit_amount' => 0,
            'credit_amount' => (float) $amount,
            'shortcut_dimension_1_code' => $expense->shortcut_dimension_1_code,
        ]);

        // Debit target expense account (with new dimensions)
        $this->postingService->createGlEntry([
            'chart_of_account_id' => $expense->expense_account_id,
            'posting_date' => now(),
            'document_type' => 'ALLOCATION',
            'document_number' => $expense->document_no,
            'description' => "Allocation IN to {$alloc['department']}",
            'debit_amount' => (float) $amount,
            'credit_amount' => 0,
            'shortcut_dimension_1_code' => $alloc['department'],
            'shortcut_dimension_2_code' => $alloc['project'] ?? null,
        ]);
    }
}
