<?php

// app/Services/PostingService.php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\Item;
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

        if (! $setup) {
            throw new \Exception("General Posting Setup missing for customer {$customer->customer_number} and item {$item->item_number}");
        }

        $entries = [];

        DB::transaction(function () use (
            $customer, $item, $quantity, $unitPrice, $unitCost,
            $postingDate, $documentNumber, $setup, &$entries
        ) {
            $totalRevenue = $quantity * $unitPrice;
            $totalCost = $quantity * $unitCost;

            // 1. A/R Entry (Debit)
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

            // 2. Revenue Entry (Credit)
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

            // 3. COGS Entry (Debit) - only for inventory items
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

                // 4. Inventory Entry (Credit)
                $inventoryAccount = $item->getInventoryAccount();
                if ($inventoryAccount) {
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
        });

        return $entries;
    }

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

        if (! $setup) {
            throw new \Exception("General Posting Setup missing for vendor {$vendor->vendor_number} and item {$item->item_number}");
        }

        $entries = [];

        DB::transaction(function () use (
            $vendor, $item, $quantity, $unitCost,
            $postingDate, $documentNumber, $setup, &$entries
        ) {
            $totalCost = $quantity * $unitCost;

            // 1. Inventory Entry (Debit) - for inventory items
            if ($item->isInventoryItem()) {
                $inventoryAccount = $item->getInventoryAccount();
                if ($inventoryAccount) {
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
                }
            } else {
                // Expense entry for non-inventory
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

            // 2. A/P Entry (Credit)
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $vendor->getPayablesAccount()->id,
                'debit_amount' => 0,
                'credit_amount' => $totalCost,
                'source_type' => 'VENDOR',
                'source_number' => $vendor->vendor_number,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Purchase from {$vendor->name}",
            ]);
        });

        return $entries;
    }

    /**
     * Create a G/L entry
     */
    private function createGlEntry(array $data): GlEntry
    {
        $data['transaction_number'] = $this->transactionNumber;
        $data['entry_number'] = $this->getNextEntryNumber();
        $data['amount'] = $data['debit_amount'] - $data['credit_amount'];
        $data['document_date'] = $data['posting_date'];
        $data['user_id'] = auth()->id();

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
     * Post customer payment receipt
     */
    public function postPaymentReceipt(
        Customer $customer,
        float $amount,
        BankAccount $bankAccount,
        float $discount,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $entries = [];
        $arAccount = $customer->customerPostingGroup->receivablesAccount;
        $bankGlAccount = $bankAccount->glAccount;

        // 1. Debit: Bank (increase cash)
        $entries[] = GlEntry::create([
            'entry_number' => $this->getNextEntryNumber(),
            'transaction_number' => $this->transactionNumber,
            'chart_of_account_id' => $bankGlAccount->id,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $amount,
            'source_type' => 'BANK',
            'source_number' => $bankAccount->account_code,
            'document_type' => 'PAYMENT_RECEIPT',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Payment from {$customer->name}",
        ]);

        // 2. Credit: A/R (decrease receivable)
        $entries[] = GlEntry::create([
            'entry_number' => $this->getNextEntryNumber(),
            'transaction_number' => $this->transactionNumber,
            'chart_of_account_id' => $arAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $amount + $discount,
            'amount' => -($amount + $discount),
            'source_type' => 'CUSTOMER',
            'source_number' => $customer->customer_number,
            'document_type' => 'PAYMENT_RECEIPT',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Payment received - {$documentNumber}",
        ]);

        // 3. Debit: Discount Given (if applicable)
        if ($discount > 0) {
            $discountAccount = ChartOfAccount::where('account_number', '70400')->first(); // Discount Given

            $entries[] = GlEntry::create([
                'entry_number' => $this->getNextEntryNumber(),
                'transaction_number' => $this->transactionNumber,
                'chart_of_account_id' => $discountAccount->id,
                'debit_amount' => $discount,
                'credit_amount' => 0,
                'amount' => $discount,
                'source_type' => 'CUSTOMER',
                'source_number' => $customer->customer_number,
                'document_type' => 'PAYMENT_RECEIPT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => 'Early payment discount',
            ]);
        }

        return $entries;
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
}
