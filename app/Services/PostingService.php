<?php

// app/Services/PostingService.php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\GeneralPostingSetup;
use App\Models\GlEntry;
use App\Models\Item;
use App\Models\PurchaseCreditMemo;
use App\Models\SalesCreditMemo;
use App\Models\SalesInvoice;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class PostingService
{
    private int $transactionNumber;

    public function __construct()
    {
        $this->transactionNumber = $this->getNextTransactionNumber();
    }

    /**
     * Create a G/L entry
     */
    private function createGlEntry(array $data): GlEntry
    {
        $data['transaction_number'] = $this->transactionNumber;
        $data['entry_number'] = $this->getNextEntryNumber();
        $data['amount'] = ($data['debit_amount'] ?? 0) - ($data['credit_amount'] ?? 0);
        $data['user_id'] = auth()->id();
        $data['document_date'] = $data['document_date'] ?? now();
        $data['posting_date'] = $data['posting_date'] ?? now();

        return GlEntry::create($data);
    }

    private function getNextTransactionNumber(): int
    {
        return (GlEntry::max('transaction_number') ?? 0) + 1;
    }

    private function getNextEntryNumber(): int
    {
        return (GlEntry::max('entry_number') ?? 0) + 1;
    }

    /**
     * Post a sales transaction
     */
    /**
     * Post a sales transaction
     */
    public function postSale(
        Customer $customer,
        Item $item,
        float $quantity,
        float $unitPrice,
        float $unitCost,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $setup = $customer->getPostingSetupFor($item);
        if (!$setup) {
            throw new \Exception("General Posting Setup missing for customer {$customer->customer_number} and item {$item->item_number}");
        }

        return DB::transaction(function () use ($customer, $item, $quantity, $unitPrice, $unitCost, $postingDate, $documentNumber, $setup) {
            $entries = [];
            $totalRevenue = $quantity * $unitPrice;
            $totalCost = $quantity * $unitCost;

            // 1. Debit A/R
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $customer->getReceivablesAccount()->id,
                'debit_amount' => $totalRevenue,
                'credit_amount' => 0,
                'source_type' => 'CUSTOMER',
                'source_number' => $customer->customer_number,
                'document_type' => 'SALES_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Sale to {$customer->name}",
            ]);

            // 2. Credit Revenue
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $setup->getSalesAccount()->id,
                'debit_amount' => 0,
                'credit_amount' => $totalRevenue,
                'source_type' => 'ITEM',
                'source_number' => $item->item_number,
                'document_type' => 'SALES_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Revenue: {$item->description}",
            ]);

            // 3. COGS and Inventory
            if ($item->isInventoryItem()) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $setup->getCogsAccount()->id,
                    'debit_amount' => $totalCost,
                    'credit_amount' => 0,
                    'source_type' => 'ITEM',
                    'source_number' => $item->item_number,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => "COGS: {$item->description}",
                ]);

                if ($inventoryAccount = $item->getInventoryAccount()) {
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $inventoryAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $totalCost,
                        'source_type' => 'ITEM',
                        'source_number' => $item->item_number,
                        'document_type' => 'SALES_INVOICE',
                        'document_number' => $documentNumber,
                        'posting_date' => $postingDate,
                        'description' => "Inventory reduction: {$item->description}",
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * Post a purchase transaction
     */

    /**
     * Post a purchase transaction
     */
    public function postPurchase(
        Vendor $vendor,
        Item $item,
        float $quantity,
        float $unitCost,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $setup = $vendor->getPostingSetupFor($item);
        if (!$setup) {
            throw new \Exception("General Posting Setup missing for vendor {$vendor->vendor_number} and item {$item->item_number}");
        }

        return DB::transaction(function () use ($vendor, $item, $quantity, $unitCost, $postingDate, $documentNumber, $setup) {
            $entries = [];
            $totalCost = $quantity * $unitCost;

            if ($item->isInventoryItem() && $inventoryAccount = $item->getInventoryAccount()) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $inventoryAccount->id,
                    'debit_amount' => $totalCost,
                    'credit_amount' => 0,
                    'source_type' => 'ITEM',
                    'source_number' => $item->item_number,
                    'document_type' => 'PURCHASE_INVOICE',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => "Inventory receipt: {$item->description}",
                ]);
            } else {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $setup->getPurchaseAccount()->id,
                    'debit_amount' => $totalCost,
                    'credit_amount' => 0,
                    'source_type' => 'ITEM',
                    'source_number' => $item->item_number,
                    'document_type' => 'PURCHASE_INVOICE',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => "Purchase: {$item->description}",
                ]);
            }

            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $vendor->getPayablesAccount()->id,
                'debit_amount' => 0,
                'credit_amount' => $totalCost,
                'source_type' => 'VENDOR',
                'source_number' => $vendor->vendor_number,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Payable to {$vendor->name}",
            ]);

            return $entries;
        });
    }

    /**
     * Create a G/L entry
     */
//    private function createGlEntry(array $data): GlEntry
//    {
//        $data['transaction_number'] = $this->transactionNumber;
//        $data['entry_number'] = $this->getNextEntryNumber();
//        $data['amount'] = $data['debit_amount'] - $data['credit_amount'];
//        $data['document_date'] = now();
//        $data['posting_date'] = now();
//        $data['user_id'] = auth()->id();
//
//        return GlEntry::create($data);
//    }
//
//    private function getNextTransactionNumber(): int
//    {
//        return (GlEntry::max('transaction_number') ?? 0) + 1;
//    }
//
//    private function getNextEntryNumber(): int
//    {
//        return (GlEntry::max('entry_number') ?? 0) + 1;
//    }

    /**
     * Post customer payment receipt
     */
    /**
     * Post a customer payment receipt
     */
    public function postPaymentReceipt(Customer $customer, float $amount, BankAccount $bankAccount, float $discount, \DateTime $postingDate, string $documentNumber): array
    {
        $arAccount = $customer->customerPostingGroup?->receivablesAccount;
        $bankGlAccount = $bankAccount->glAccount;

        if (!$arAccount || !$bankGlAccount) {
            throw new \Exception("Missing account setup for payment receipt.");
        }

        return DB::transaction(function () use ($customer, $amount, $bankAccount, $discount, $postingDate, $documentNumber, $arAccount, $bankGlAccount) {
            $entries = [];

            // Debit Bank
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $bankGlAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'source_type' => 'BANK',
                'source_number' => $bankAccount->account_code,
                'document_type' => 'PAYMENT_RECEIPT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Payment from {$customer->name}",
            ]);

            // Credit A/R
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $arAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount + $discount,
                'source_type' => 'CUSTOMER',
                'source_number' => $customer->customer_number,
                'document_type' => 'PAYMENT_RECEIPT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Payment received - {$documentNumber}",
            ]);

            // Discount Given
            if ($discount > 0 && $discountAccount = ChartOfAccount::where('account_number', '70400')->first()) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $discountAccount->id,
                    'debit_amount' => $discount,
                    'credit_amount' => 0,
                    'source_type' => 'CUSTOMER',
                    'source_number' => $customer->customer_number,
                    'document_type' => 'PAYMENT_RECEIPT',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => 'Early payment discount',
                ]);
            }

            return $entries;
        });
    }
    /**
     * Post vendor payment disbursement
     */
    public function postPaymentDisbursement(
        Vendor $vendor,
        float $amount,
        BankAccount $bankAccount,
        float $discount,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $entries = [];
        $apAccount = $vendor->vendorPostingGroup->payablesAccount;
        $bankGlAccount = $bankAccount->glAccount;

        // 1. Debit: A/P (decrease payable)
        $entries[] = GlEntry::create([
            'entry_number' => $this->getNextEntryNumber(),
            'transaction_number' => $this->transactionNumber,
            'chart_of_account_id' => $apAccount->id,
            'debit_amount' => $amount + $discount,
            'credit_amount' => 0,
            'amount' => $amount + $discount,
            'source_type' => 'VENDOR',
            'source_number' => $vendor->vendor_number,
            'document_type' => 'PAYMENT_DISBURSEMENT',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Payment to {$vendor->name}",
        ]);

        // 2. Credit: Bank (decrease cash)
        $entries[] = GlEntry::create([
            'entry_number' => $this->getNextEntryNumber(),
            'transaction_number' => $this->transactionNumber,
            'chart_of_account_id' => $bankGlAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'amount' => -$amount,
            'source_type' => 'BANK',
            'source_number' => $bankAccount->account_code,
            'document_type' => 'PAYMENT_DISBURSEMENT',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Payment to {$vendor->name}",
        ]);

        // 3. Credit: Discount Received (if applicable)
        if ($discount > 0) {
            $discountAccount = ChartOfAccount::where('account_number', '70400')->first(); // Discount Received

            $entries[] = GlEntry::create([
                'entry_number' => $this->getNextEntryNumber(),
                'transaction_number' => $this->transactionNumber,
                'chart_of_account_id' => $discountAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $discount,
                'amount' => -$discount,
                'source_type' => 'VENDOR',
                'source_number' => $vendor->vendor_number,
                'document_type' => 'PAYMENT_DISBURSEMENT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => 'Early payment discount received',
            ]);
        }

        return $entries;
    }

    public function postPurchaseLine(
        Vendor $vendor,
        Item $item,
        float $quantity,
        float $unitCost,
        float $lineTotal,
        \DateTime $postingDate,
        string $documentNumber,
        string $description
    ): array {
        $setup = $vendor->getPostingSetupFor($item);

        if (! $setup) {
            throw new \Exception("Posting setup missing for vendor {$vendor->vendor_number} and item {$item->item_number}");
        }

        $entries = [];

        // Inventory item
        if ($item->isInventoryItem()) {
            $inventoryAccount = $item->getInventoryAccount();

            if (! $inventoryAccount) {
                throw new \Exception("Inventory account missing for item {$item->item_number}");
            }

            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $inventoryAccount->id,
                'debit_amount' => $lineTotal,
                'credit_amount' => 0,
                'source_type' => 'ITEM',
                'source_number' => $item->item_number,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Inventory receipt: {$description}",
            ]);
        } else {
            // Expense
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $setup->getPurchaseAccount()->id,
                'debit_amount' => $lineTotal,
                'credit_amount' => 0,
                'source_type' => 'ITEM',
                'source_number' => $item->item_number,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Purchase expense: {$description}",
            ]);
        }

        return $entries;
    }

    public function postVendorPayable(
        Vendor $vendor,
        float $amount,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $entries = [];

        $entries[] = $this->createGlEntry([
            'chart_of_account_id' => $vendor->getPayablesAccount()->id,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'source_type' => 'VENDOR',
            'source_number' => $vendor->vendor_code,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Payable to {$vendor->vendor_name}",
        ]);

        return $entries;
    }

    public function post(array $lines, array $dimensions = [], $source = null)
    {
        return DB::transaction(function () use ($lines, $dimensions, $source) {

            $entries = [];

            foreach ($lines as $line) {
                $entry = GlEntry::create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'posting_date' => now(),
                    'reference' => $line['reference'] ?? null,
                    'source_type' => $source ? get_class($source) : null,
                    'source_id' => $source?->id,
                ]);

                $entries[] = $entry;

                // Attach dimensions
                foreach ($dimensions as $dim) {
                    $entry->dimensions()->attach([
                        $dim['dimension_id'] => [
                            'dimension_value_id' => $dim['dimension_value_id'],
                        ],
                    ]);
                }
            }

            // Validate double entry
            $this->validateBalanced($entries);

            return $entries;
        });
    }

    private function validateBalanced($entries)
    {
        $totalDebit = collect($entries)->sum('debit');
        $totalCredit = collect($entries)->sum('credit');

        if ($totalDebit !== $totalCredit) {
            throw new \Exception('Journal is not balanced');
        }
    }

    /**
     * @throws \Throwable
     */
    public function postSalesInvoice(SalesInvoice $invoice): array
    {
        return DB::transaction(function () use ($invoice) {

            $invoice->load('lines', 'customer');

            $entries = [];

            $customer = $invoice->customer;
            $customerGroupId = $customer->general_business_posting_group_id;
            $receivablesAccount = $customer->getReceivablesAccount();

            if (!$receivablesAccount) {
                throw new \Exception("Customer '{$customer->name}' is missing a receivables account.");
            }

            foreach ($invoice->lines as $line) {

                $item = Item::find($line->item_id);
                if (!$item) {
                    throw new \Exception("Item with ID {$line->item_id} not found.");
                }

                $productGroupId = $item->inventory_posting_group_id;

                // Fetch posting setup for this customer × item group
                $postingSetup = GeneralPostingSetup::where([
                    'general_business_posting_group_id' => $customerGroupId,
                    'general_product_posting_group_id' => $productGroupId,
                ])->first();

                // In PostingService.php around line 515-520, replace:
                if (!$postingSetup) {
                    throw new \Exception(
                        "No posting setup found for customer '{$customer->name}' (Group: {$customerGroupId}) " .
                        "and item '{$item->name}' (Group: {$productGroupId}). " .
                        "Please configure General Posting Setup for Business Group ID {$customerGroupId} " .
                        "and Product Group ID {$productGroupId}."
                    );
                }

                $salesAccount = $postingSetup->getSalesAccount();
                $cogsAccount = $postingSetup->getCogsAccount();
                $inventoryAccount = $item->getInventoryAccount();

                if (!$salesAccount) {
                    throw new \Exception("Missing sales account for item '{$item->name}'.");
                }

                $lineRevenue = $line->quantity * $line->unit_price;
                $lineCost = $line->quantity * ($item->unit_cost ?? 0);

                // 1. Accounts Receivable (Debit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $receivablesAccount->id,
                    'debit_amount' => $lineRevenue,
                    'credit_amount' => 0,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $invoice->invoice_number,
                    'posting_date' => $invoice->posting_date,
                    'document_date' => $invoice->posting_date,
                    'description' => "Invoice {$invoice->invoice_number}",
                ]);

                // 2. Revenue (Credit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $salesAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $lineRevenue,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $invoice->invoice_number,
                    'posting_date' => $invoice->posting_date,
                    'document_date' => $invoice->posting_date,
                    'description' => "Revenue {$item->description}",
                ]);

                // Inventory postings (only for inventory items)
                if ($item->isInventoryItem()) {

                    if (!$cogsAccount || !$inventoryAccount) {
                        throw new \Exception("Missing COGS or Inventory account for item '{$item->name}'.");
                    }

                    // 3. COGS (Debit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $cogsAccount->id,
                        'debit_amount' => $lineCost,
                        'credit_amount' => 0,
                        'document_type' => 'SALES_INVOICE',
                        'document_number' => $invoice->invoice_number,
                        'posting_date' => $invoice->posting_date,
                        'document_date' => $invoice->posting_date,
                        'description' => "COGS {$item->description}",
                    ]);

                    // 4. Inventory (Credit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $inventoryAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineCost,
                        'document_type' => 'SALES_INVOICE',
                        'document_number' => $invoice->invoice_number,
                        'posting_date' => $invoice->posting_date,
                        'document_date' => $invoice->posting_date,
                        'description' => "Inventory decrease {$item->description}",
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * @throws \Throwable
     */
    public function postPurchaseCreditMemo(PurchaseCreditMemo $memo): array
    {
        return DB::transaction(function () use ($memo) {
            $memo->load('lines', 'vendor');
            $entries = [];

            foreach ($memo->lines as $line) {
                $item = Item::find($line->item_id);
                $setup = $memo->vendor->getPostingSetupFor($item);

                $lineAmount = $line->quantity * $line->unit_cost;
                // Note: We use unit_cost for both inventory and expense reversal in purchase context

                // 1. A/P Reduction (Debit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $memo->vendor->getPayablesAccount()->id,
                    'debit_amount' => $lineAmount,
                    'credit_amount' => 0,
                    'document_type' => 'PURCHASE_CREDIT_MEMO',
                    'document_number' => $memo->document_number,
                    'posting_date' => $memo->posting_date ?? now(),
                    'description' => "Reduce payable: {$memo->document_number}",
                ]);

                if ($item->isInventoryItem()) {
                    // 2. Inventory Reduction (Credit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $item->getInventoryAccount()->id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineAmount,
                        'document_type' => 'PURCHASE_CREDIT_MEMO',
                        'document_number' => $memo->document_number,
                        'posting_date' => $memo->posting_date ?? now(),
                        'description' => "Inventory return: {$item->description}",
                    ]);
                } else {
                    // 2. Expense/Purchase Reversal (Credit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $setup->getPurchaseAccount()->id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineAmount,
                        'document_type' => 'PURCHASE_CREDIT_MEMO',
                        'document_number' => $memo->document_number,
                        'posting_date' => $memo->posting_date ?? now(),
                        'description' => "Purchase reversal: {$item->description}",
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * @throws \Throwable
     */
    public function postSalesCreditMemo(SalesCreditMemo $memo): array
    {
        return DB::transaction(function () use ($memo) {

            $memo->load('items', 'customer');

            $entries = [];

            foreach ($memo->items as $line) {

                $item = Item::find($line->item_id);
                $setup = $memo->customer->getPostingSetupFor($item);

                $lineAmount = $line->quantity * $line->price;
                $lineCost = $line->quantity * ($item->unit_cost ?? 0);

                // 1. Revenue Reversal (Debit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $setup->getSalesAccount()->id,
                    'debit_amount' => $lineAmount,
                    'credit_amount' => 0,
                    'document_type' => 'SALES_CREDIT_MEMO',
                    'document_number' => $memo->memo_number,
                    'posting_date' => $memo->effective_date,
                    'description' => "Credit Memo Revenue Reversal",
                ]);

                // 2. A/R Reduction (Credit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $memo->customer->getReceivablesAccount()->id,
                    'debit_amount' => 0,
                    'credit_amount' => $lineAmount,
                    'document_type' => 'SALES_CREDIT_MEMO',
                    'document_number' => $memo->memo_number,
                    'posting_date' => $memo->effective_date,
                    'description' => "Reduce receivable",
                ]);

                if ($item->isInventoryItem()) {

                    // 3. Inventory Increase (Debit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $item->getInventoryAccount()->id,
                        'debit_amount' => $lineCost,
                        'credit_amount' => 0,
                        'document_type' => 'SALES_CREDIT_MEMO',
                        'document_number' => $memo->memo_number,
                        'posting_date' => $memo->effective_date,
                        'description' => "Inventory return",
                    ]);

                    // 4. Reverse COGS (Credit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $setup->getCogsAccount()->id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineCost,
                        'document_type' => 'SALES_CREDIT_MEMO',
                        'document_number' => $memo->memo_number,
                        'posting_date' => $memo->effective_date,
                        'description' => "Reverse COGS",
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * Retrieve posting setup for a customer or vendor for an item.
     * Returns null if setup not found.
     */
    private function getPostingSetup($entity, Item $item)
    {
        if (method_exists($entity, 'getPostingSetupFor')) {
            return $entity->getPostingSetupFor($item);
        }

        return null;
    }

    /**
     * Get the next transaction number for GL entries
     */
//    private function getNextTransactionNumber(): int
//    {
//        return (GlEntry::max('transaction_number') ?? 0) + 1;
//    }

    /**
     * Get the next entry number for GL entries
     */
//    private function getNextEntryNumber(): int
//    {
//        return (GlEntry::max('entry_number') ?? 0) + 1;
//    }

    /**
     * Create a GL entry
     */
//    private function createGlEntry(array $data): GlEntry
//    {
//        // Ensure required fields
//        $data['transaction_number'] = $this->transactionNumber;
//        $data['entry_number'] = $this->getNextEntryNumber();
//        $data['amount'] = $data['debit_amount'] - $data['credit_amount'];
//        $data['document_date'] = $data['document_date'] ?? now();
//        $data['posting_date'] = $data['posting_date'] ?? now();
//        $data['user_id'] = auth()->id() ?? null;
//
//        return GlEntry::create($data);
//    }

    /**
     * Validate that total debits = total credits
     */
//    private function validateBalanced(array $entries)
//    {
//        $totalDebit = collect($entries)->sum('debit_amount');
//        $totalCredit = collect($entries)->sum('credit_amount');
//
//        if ($totalDebit !== $totalCredit) {
//            throw new \Exception('Journal is not balanced');
//        }
//    }

    /**
     * Helper: Get receivables account for a customer
     */
    private function getCustomerReceivablesAccount(Customer $customer): ChartOfAccount
    {
        return $customer->getReceivablesAccount();
    }

    /**
     * Helper: Get payables account for a vendor
     */
    private function getVendorPayablesAccount(Vendor $vendor): ChartOfAccount
    {
        return $vendor->getPayablesAccount();
    }

    /**
     * Helper: Get bank GL account
     */
    private function getBankGlAccount(BankAccount $bank): ChartOfAccount
    {
        return $bank->glAccount;
    }
}
