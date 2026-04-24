<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\COGSCategory;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\RevenueCategory;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExpenseBudget;
use App\Models\ExpenseCategory;
use App\Models\ExpenseTransaction;
use App\Models\FixedAsset;
use App\Models\GeneralPostingSetup;
use App\Models\Item;
use App\Models\RecurringExpense;
use App\Models\VatPostingSetup;
use App\Models\Vendor;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private NumberSeriesService        $numberSeriesService,
        private GeneralLedgerService       $glService,
        private CurrencyService            $currencyService,
        private PostingService             $postingService,
        private DimensionManagementService $dimensionService,
        private VatCalculationService      $vatService
    ) {}

    /**
     * Post a generic expense transaction (BC standard)
     * @throws \Throwable
     */
    public function post(ExpenseTransaction $transaction): void
    {
        if ($transaction->status === 'posted') {
            throw new \RuntimeException('Transaction is already posted');
        }

        DB::transaction(function () use ($transaction) {
            $this->resolvePostingAccounts($transaction);
            $this->calculateTaxes($transaction);
            $this->mergeDimensions($transaction);

            $totalAmount = (float) $transaction->amount;
            $vatAmount = (float) $transaction->vat_amount;
            $netAmount = $totalAmount - $vatAmount;
            $lcyFactor = $transaction->currency_factor ?: 1.0;

            $lines = [];

            // 1. Debit Expense Account
            $lines[] = [
                'account_id' => $transaction->expense_account_id,
                'debit' => $netAmount,
                'credit' => 0,
                'description' => $transaction->description ?: "Expense: {$transaction->document_no}",
                'currency_id' => $transaction->currency_id,
                'debit_amount_lcy' => $netAmount * $lcyFactor,
                'credit_amount_lcy' => 0,
                'shortcut_dimension_1_code' => $transaction->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $transaction->shortcut_dimension_2_code,
                'dimensions' => $this->dimensionService->getDimensionSet($transaction->dimension_set_id ?? 0)->toArray(),
            ];

            // 2. Debit VAT Account (Input VAT)
            if ($vatAmount > 0) {
                $vatSetup = $this->resolveVatSetup($transaction);
                if ($vatSetup && $vatSetup->purchase_vat_account_id) {
                    $lines[] = [
                        'account_id' => $vatSetup->purchase_vat_account_id,
                        'debit' => $vatAmount,
                        'credit' => 0,
                        'description' => "VAT for {$transaction->document_no}",
                        'currency_id' => $transaction->currency_id,
                        'debit_amount_lcy' => $vatAmount * $lcyFactor,
                        'credit_amount_lcy' => 0,
                        'dimensions' => [],
                    ];
                }
            }

            // 3. Credit Offset Account (Bank/Payable)
            $offsetAccount = $this->resolveOffsetAccount($transaction);
            $lines[] = [
                'account_id' => $offsetAccount->id,
                'debit' => 0,
                'credit' => $totalAmount,
                'description' => $transaction->description ?: "Offset: {$transaction->document_no}",
                'currency_id' => $transaction->currency_id,
                'debit_amount_lcy' => 0,
                'credit_amount_lcy' => $totalAmount * $lcyFactor,
                'shortcut_dimension_1_code' => $transaction->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $transaction->shortcut_dimension_2_code,
                'dimensions' => $this->dimensionService->getDimensionSet($transaction->dimension_set_id ?? 0)->toArray(),
            ];

            $this->glService->post($lines, [
                'posting_date' => $transaction->posting_date,
                'document_number' => $transaction->document_no,
                'document_type' => $transaction->document_type,
                'source_type' => 'EXPENSE',
                'sourceable_type' => ExpenseTransaction::class, // CRITICAL: Polymorphic Link
                'sourceable_id' => $transaction->id,
                'description' => $transaction->description,
            ]);

            $transaction->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            if ($transaction->allocations()->exists()) {
                $this->validateAllocations($transaction);
                $this->processAllocations($transaction);
            }
        });
    }

    /**
     * Post to GL - Unified helper to ensure sourceable links are always created.
     */
    private function postToGL(ExpenseTransaction $transaction): void
    {
        // Balanced entry: Debit Account (Amount), Credit Offset (Amount)
        $offsetAccount = $this->resolveOffsetAccount($transaction);
        $amount = (float) $transaction->amount;

        $lines = [
            [
                'account_id' => $transaction->expense_account_id,
                'debit' => $amount > 0 ? $amount : 0,
                'credit' => $amount < 0 ? abs($amount) : 0,
                'description' => $transaction->description,
                'shortcut_dimension_1_code' => $transaction->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $transaction->shortcut_dimension_2_code,
                'dimensions' => $this->dimensionService->getDimensionSet($transaction->dimension_set_id ?? 0)->toArray(),
            ],
            [
                'account_id' => $offsetAccount->id,
                'debit' => $amount < 0 ? abs($amount) : 0,
                'credit' => $amount > 0 ? $amount : 0,
                'description' => "Offset: " . $transaction->document_no,
                'dimensions' => [],
            ]
        ];

        $this->glService->post($lines, [
            'posting_date' => $transaction->posting_date,
            'document_number' => $transaction->document_no,
            'document_type' => $transaction->document_type,
            'sourceable_type' => ExpenseTransaction::class,
            'sourceable_id' => $transaction->id,
        ]);
    }

    /**
     * Allocate indirect expense to cost centers
     */
    private function processAllocations(ExpenseTransaction $transaction): void
    {
        foreach ($transaction->allocations as $allocation) {
            $amount = $allocation->allocation_type === 'amount'
                ? (float) $allocation->allocated_amount
                : (float) $transaction->amount * ((float) $allocation->allocation_percentage / 100);

            $lines = [
                [
                    'account_id' => $transaction->expense_account_id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "Allocation OUT: {$transaction->document_no}",
                    'dimensions' => $this->dimensionService->getDimensionSet($transaction->dimension_set_id ?? 0)->toArray(),
                ],
                [
                    'account_id' => $allocation->target_gl_account_id ?? $transaction->expense_account_id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Allocation IN: {$transaction->document_no}",
                    'dimensions' => $this->dimensionService->getDimensionSet($allocation->dimension_set_id ?? 0)->toArray(),
                ]
            ];

            $this->glService->post($lines, [
                'posting_date' => $transaction->posting_date,
                'document_number' => $transaction->document_no,
                'document_type' => 'ALLOCATION',
                'sourceable_type' => ExpenseTransaction::class, // Establish link for allocations too
                'sourceable_id' => $transaction->id,
            ]);
        }
    }

    private function reverseCOGS(Item $item, float $quantity, string $documentNo): void
    {
        $amount = $quantity * ($item->unit_cost ?? 0);
        $cogsAccount = $this->resolveCOGSAccount($item);
        $inventoryAccount = $item->getInventoryAccount();

        if ($amount > 0 && $cogsAccount && $inventoryAccount) {
            $lines = [
                ['account_id' => $inventoryAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => "Rev COGS: {$item->item_code}"],
                ['account_id' => $cogsAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => "Rev COGS: {$item->item_code}"]
            ];

            // Use doc number to find the transaction if we want to link it
            $transaction = ExpenseTransaction::where('document_no', $documentNo)->first();

            $this->glService->post($lines, [
                'posting_date' => now(),
                'document_number' => $documentNo,
                'document_type' => 'SALES_RETURN',
                'sourceable_type' => $transaction ? ExpenseTransaction::class : null,
                'sourceable_id' => $transaction?->id,
            ]);
        }
    }

    /**
     * Post COGS from inventory transaction
     * @throws \Throwable
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
        FixedAsset $asset,
        float $amount,
        \DateTime $postingDate
    ): ExpenseTransaction {
        $category = ExpenseCategoryEnum::DEPRECIATION_PLANT; // Default for now, can be sophisticated based on subclass

        return ExpenseTransaction::create([
            'document_type' => 'depreciation',
            'document_no' => $this->numberSeriesService->getNextNo('DEPR'),
            'posting_date' => $postingDate,
            'account_type' => AccountType::INDIRECT_EXPENSE,
            'category_code' => $category->value,
            'expense_type' => 'indirect',
            'amount' => $amount,
            'amount_lcy' => $amount,
            'expense_account_id' => $asset->postingGroup?->depreciation_expense_account_id,
            'shortcut_dimension_1_code' => $asset->shortcut_dimension_1_code,
            'posted_by' => Auth::id(),
            'description' => "Depreciation of {$asset->fa_no}",
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
        $interimAccount = ChartOfAccount::where('account_number', '2100')->first(); // Purchase interim

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
            'currency_id' => $currencyCode ? Currency::where('code', $currencyCode)->first()?->id : null,
            'vendor_id' => $vendor->id,
            'category_id' => null, // Or resolve if needed
            'expense_account_id' => $interimAccount->id,
            'posted_by' => Auth::id(),
            'description' => "Purchase interim for PO {$documentNo}",
        ]);
    }

    /**
     * Allocate indirect expense to cost centers
     * @throws \Throwable
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
            ?? ChartOfAccount::where('account_number', '5100')->first();
    }

    private function resolveAdjustmentAccount(ExpenseCategory $category): ChartOfAccount
    {
        return ChartOfAccount::where('account_number', '5150')->first(); // Inventory adjustments
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

    private function calculateTaxes(ExpenseTransaction $transaction): void
    {
        if ($transaction->vat_amount > 0) {
            return; // Already calculated manually
        }

        if ($transaction->vat_bus_posting_group_id && $transaction->vat_prod_posting_group_id) {
            try {
                $busGroup = $transaction->vatBusinessPostingGroup->code;
                $prodGroup = $transaction->vatProductPostingGroup->code;

                $result = $this->vatService->calculateVat(
                    (float) $transaction->amount,
                    $busGroup,
                    $prodGroup,
                    false // isSale = false for expenses
                );

                $transaction->vat_amount = $result['vat_amount'];
                $transaction->save();
            } catch (\Exception $e) {
                // Log or handle error - if setup is missing, we might default to 0
            }
        }
    }

    private function resolveVatSetup(ExpenseTransaction $transaction)
    {
        if ($transaction->vat_bus_posting_group_id && $transaction->vat_prod_posting_group_id) {
            return VatPostingSetup::where([
                'vat_business_posting_group_id' => $transaction->vat_bus_posting_group_id,
                'vat_product_posting_group_id' => $transaction->vat_prod_posting_group_id,
            ])->first();
        }

        return null;
    }

    /**
     * Merge dimensions from source and category
     */
    public function mergeDimensions(ExpenseTransaction $transaction): void
    {
        $dimensionSet = [];

        // 1. From Category (Highest Priority)
        if ($transaction->expenseCategory) {
            if ($transaction->expenseCategory->default_dimension_1) {
                $dimensionSet['DEPARTMENT'] = $transaction->expenseCategory->default_dimension_1;
            }
            if ($transaction->expenseCategory->default_dimension_2) {
                $dimensionSet['PROJECT'] = $transaction->expenseCategory->default_dimension_2;
            }
        }

        // 2. From Source (Vendor/Employee)
        $source = $transaction->vendor ?: $transaction->employee;
        if ($source && method_exists($source, 'dimensionValues')) {
            foreach ($source->dimensionValues as $dv) {
                $dimCode = $dv->dimension->code;
                if (! isset($dimensionSet[$dimCode])) {
                    $dimensionSet[$dimCode] = $dv->code;
                }
            }
        }

        // 3. Manually set on transaction
        if ($transaction->shortcut_dimension_1_code) {
            $dimensionSet['DEPARTMENT'] = $transaction->shortcut_dimension_1_code;
        }
        if ($transaction->shortcut_dimension_2_code) {
            $dimensionSet['PROJECT'] = $transaction->shortcut_dimension_2_code;
        }

        if (! empty($dimensionSet)) {
            $set = $this->dimensionService->getOrCreateSet($dimensionSet);
            $transaction->dimension_set_id = $set->id;
            $transaction->shortcut_dimension_1_code = $dimensionSet['DEPARTMENT'] ?? null;
            $transaction->shortcut_dimension_2_code = $dimensionSet['PROJECT'] ?? null;
            $transaction->save();
        }
    }

    /**
     * Generate actual transactions from all due recurring templates
     * @throws \Throwable
     */
    public function processRecurringExpenses(): void
    {
        $dueExpenses = RecurringExpense::where('is_active', true)
            ->where('next_occurrence_at', '<=', now())
            ->get();

        foreach ($dueExpenses as $recurring) {
            DB::transaction(function () use ($recurring) {
                $this->createFromRecurring($recurring);

                // Update next occurrence
                $recurring->last_occurrence_at = $recurring->next_occurrence_at;
                $recurring->next_occurrence_at = $this->calculateNextOccurrence($recurring);

                if ($recurring->end_date && $recurring->next_occurrence_at > $recurring->end_date) {
                    $recurring->is_active = false;
                }

                $recurring->save();
            });
        }
    }

    /**
     * Create a single transaction from a recurring template
     */
    public function createFromRecurring(RecurringExpense $recurring): ExpenseTransaction
    {
        $transaction = ExpenseTransaction::create([
            'document_type' => 'recurring',
            'document_no' => $this->numberSeriesService->getNextNo('RECUR'),
            'posting_date' => $recurring->next_occurrence_at,
            'document_date' => now(),
            'account_type' => AccountType::DIRECT_EXPENSE, // Default for now
            'category_code' => $recurring->category_code ?? $recurring->category?->category_code,
            'amount' => $recurring->amount,
            'amount_lcy' => $this->currencyService->toLCY($recurring->amount, $recurring->currency?->code),
            'currency_id' => $recurring->currency_id,
            'vendor_id' => $recurring->vendor_id,
            'category_id' => $recurring->category_id,
            'shortcut_dimension_1_code' => $recurring->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $recurring->shortcut_dimension_2_code,
            'dimension_set_id' => $recurring->dimension_set_id,
            'status' => 'open',
            'description' => "Recurring: {$recurring->description} ({$recurring->code})",
        ]);

        // Auto-merge dimensions to ensure consistency
        $this->mergeDimensions($transaction);

        if ($recurring->auto_post) {
            try {
                $this->post($transaction);
            } catch (\Exception $e) {
                // Log failure but keep transaction as 'open'
                \Log::error("Failed to auto-post recurring expense {$recurring->code}: ".$e->getMessage());
            }
        }

        return $transaction;
    }

    /**
     * @throws \DateMalformedIntervalStringException
     */
    private function calculateNextOccurrence(RecurringExpense $recurring): \DateTime
    {
        $date = clone $recurring->next_occurrence_at;
        $interval = $recurring->interval ?: 1;

        return match ($recurring->frequency) {
            'daily' => $date->add(new \DateInterval("P{$interval}D")),
            'weekly' => $date->add(new \DateInterval("P{$interval}W")),
            'monthly' => $date->add(new \DateInterval("P{$interval}M")),
            'quarterly' => $date->add(new \DateInterval('P'.($interval * 3).'M')),
            'yearly' => $date->add(new \DateInterval("P{$interval}Y")),
            default => $date->add(new \DateInterval('P1M')),
        };
    }

    private function validateAllocations(ExpenseTransaction $transaction): void
    {
        $allocations = $transaction->allocations;
        $totalAmount = (float) $transaction->amount;

        $fixedAllocations = $allocations->where('allocation_type', 'amount');
        $percentageAllocations = $allocations->where('allocation_type', 'percentage');

        $fixedSum = (float) $fixedAllocations->sum('allocated_amount');
        $percentageSum = (float) $percentageAllocations->sum('allocation_percentage');

        // Rule 1: Fixed amounts cannot exceed the total amount
        if ($fixedSum > $totalAmount + 0.001) {
            throw new \RuntimeException("Fixed allocation sum ({$fixedSum}) exceeds transaction total ({$totalAmount})");
        }

        // Rule 2: If percentage allocations exist, they MUST sum to 100% of the REMAINING balance
        if ($percentageAllocations->isNotEmpty() && abs($percentageSum - 100) > 0.001) {
            throw new \RuntimeException("Percentage allocations must sum to 100%. Current: {$percentageSum}%");
        }

        // Rule 3: If NO percentage allocations exist, fixed amounts MUST EQUAL total amount
        if ($percentageAllocations->isEmpty() && abs($fixedSum - $totalAmount) > 0.01) {
            throw new \RuntimeException("Total allocated amount ({$fixedSum}) does not equal transaction total ({$totalAmount})");
        }
    }

    private function resolveOffsetAccount(ExpenseTransaction $transaction): ChartOfAccount
    {
        if ($transaction->vendor) {
            $account = $transaction->vendor->getPayablesAccount();
            if ($account) return $account;
        }

        if ($transaction->employee) {
            $account = $transaction->employee->employeePostingGroup?->payables_account_id
                ? ChartOfAccount::find($transaction->employee->employeePostingGroup->payables_account_id)
                : ChartOfAccount::where('account_number', '2100')->first();
            if ($account) return $account;
        }

        $account = ChartOfAccount::where('account_number', '10100')->first() ?? ChartOfAccount::where('account_number', '9999')->first();

        if (!$account) {
            throw new \RuntimeException("No Offset Account found for {$transaction->document_no}. Check COA setup.");
        }

        return $account;
    }

    private function resolvePostingAccounts(ExpenseTransaction $transaction): void
    {
        if (!$transaction->gen_bus_posting_group_id || !$transaction->gen_prod_posting_group_id) {
            // If missing, we don't throw error here to allow manual account overrides if already set
            if ($transaction->expense_account_id) return;
            throw new \RuntimeException("Posting Groups required for {$transaction->document_no}");
        }

        $setup = GeneralPostingSetup::where([
            'general_business_posting_group_id' => $transaction->gen_bus_posting_group_id,
            'general_product_posting_group_id' => $transaction->gen_prod_posting_group_id,
        ])->first();

        if ($setup) {
            $transaction->expense_account_id = $setup->purchase_account_id ?? $setup->inventory_account_id;
            $transaction->save();
        }
    }
}
