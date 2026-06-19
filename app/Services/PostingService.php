<?php

// app/Services/PostingService.php

namespace App\Services;

use App\Enums\FAStatus;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\GeneralPostingSetup;
use App\Models\GlEntry;
use App\Models\Item;
use App\Models\PaymentApplication;
use App\Models\PurchaseCreditMemo;
use App\Models\SalesCreditMemo;
use App\Models\SalesInvoice;
use App\Models\VatPostingSetup;
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
    public function createGlEntry(array $data): GlEntry
    {
        $data['transaction_number'] = $data['transaction_number'] ?? $this->transactionNumber;
        $data['entry_number'] = $this->getNextEntryNumber();

        $debit = $data['debit_amount'] ?? 0;
        $credit = $data['credit_amount'] ?? 0;
        $data['amount'] = $debit - $credit;

        // Handle Multi-Currency
        if (isset($data['currency_id'])) {
            $currency = Currency::find($data['currency_id']);
            if ($currency) {
                $rate = $data['exchange_rate'] ?? $currency->getExchangeRate($data['posting_date'] ?? null);
                $data['exchange_rate'] = $rate;
                $data['debit_amount_lcy'] = $currency->toLCY($debit, $rate);
                $data['credit_amount_lcy'] = $currency->toLCY($credit, $rate);
                $data['amount_lcy'] = $data['debit_amount_lcy'] - $data['credit_amount_lcy'];
            }
        } else {
            // LCY defaults
            $data['debit_amount_lcy'] = $debit;
            $data['credit_amount_lcy'] = $credit;
            $data['amount_lcy'] = $data['amount'];
        }

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
        if (! $setup) {
            throw new \Exception("General Posting Setup missing for customer {$customer->customer_number} and item {$item->item_code}");
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
                'source_number' => $item->item_code,
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
                    'source_number' => $item->item_code,
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
                        'source_number' => $item->item_code,
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
        if (! $setup) {
            throw new \Exception("General Posting Setup missing for vendor {$vendor->vendor_number} and item {$item->item_code}");
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
                    'source_number' => $item->item_code,
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
                    'source_number' => $item->item_code,
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
    /**
     * Post a customer payment receipt
     */
    public function postPaymentReceipt(
        Customer $customer,
        float $amount,
        BankAccount $bankAccount,
        float $discount,
        \DateTime $postingDate,
        string $documentNumber,
        ?int $currencyId = null,
        ?float $exchangeRate = null
    ): array {
        $arAccount = $customer->customerPostingGroup?->receivablesAccount;
        $bankGlAccount = $bankAccount->glAccount;

        if (! $arAccount || ! $bankGlAccount) {
            throw new \Exception('Missing account setup for payment receipt.');
        }

        return DB::transaction(function () use ($customer, $amount, $bankAccount, $discount, $postingDate, $documentNumber, $arAccount, $bankGlAccount, $currencyId, $exchangeRate) {
            $entries = [];

            // Debit Bank
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $bankGlAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'source_type' => 'BANK',
                'source_number' => $bankAccount->account_code,
                'document_type' => 'PAYMENT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'description' => "Payment from {$customer->name}",
            ]);

            // Credit A/R
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $arAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount + $discount,
                'source_type' => 'CUSTOMER',
                'source_number' => $customer->customer_number,
                'document_type' => 'PAYMENT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
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
                    'document_type' => 'PAYMENT',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate,
                    'description' => 'Early payment discount',
                ]);
            }

            return $entries;
        });
    }

    /**
     * Post vendor payment disbursement
     */
    /**
     * Post vendor payment disbursement
     */
    public function postPaymentDisbursement(
        Vendor $vendor,
        float $amount,
        BankAccount $bankAccount,
        float $discount,
        \DateTime $postingDate,
        string $documentNumber,
        ?int $currencyId = null,
        ?float $exchangeRate = null
    ): array {
        $apAccount = $vendor->vendorPostingGroup?->payablesAccount;
        $bankGlAccount = $bankAccount->glAccount;

        if (! $apAccount || ! $bankGlAccount) {
            throw new \Exception('Missing account setup for payment disbursement.');
        }

        return DB::transaction(function () use ($vendor, $amount, $bankAccount, $discount, $postingDate, $documentNumber, $apAccount, $bankGlAccount, $currencyId, $exchangeRate) {
            $entries = [];

            // 1. Debit: A/P (decrease payable)
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $apAccount->id,
                'debit_amount' => $amount + $discount,
                'credit_amount' => 0,
                'source_type' => 'VENDOR',
                'source_number' => $vendor->vendor_number,
                'document_type' => 'PAYMENT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'description' => "Payment to {$vendor->name}",
            ]);

            // 2. Credit: Bank (decrease cash)
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $bankGlAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'source_type' => 'BANK',
                'source_number' => $bankAccount->account_code,
                'document_type' => 'PAYMENT',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'description' => "Payment to {$vendor->name}",
            ]);

            // 3. Credit: Discount Received (if applicable - 70400 matches ChartOfAccountSeeder)
            if ($discount > 0) {
                $discountAccount = ChartOfAccount::where('account_number', '70400')->first();
                if ($discountAccount) {
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $discountAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $discount,
                        'source_type' => 'VENDOR',
                        'source_number' => $vendor->vendor_number,
                        'document_type' => 'PAYMENT',
                        'document_number' => $documentNumber,
                        'posting_date' => $postingDate,
                        'currency_id' => $currencyId,
                        'exchange_rate' => $exchangeRate,
                        'description' => 'Early payment discount received',
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * Post Realized Exchange Gain or Loss
     */
    public function postRealizedGainLoss(PaymentApplication $application): array
    {
        $currency = $application->currency;
        if (! $currency) {
            return [];
        }

        $gainLossAmount = $application->gain_loss_amount; // LCY amount
        if (abs($gainLossAmount) < 0.001) {
            return [];
        }

        $payment = $application->payment;
        $party = $payment->party;

        return DB::transaction(function () use ($application, $currency, $gainLossAmount, $payment, $party) {
            $entries = [];

            // 1. Adjust the Accounts Receivable/Payable (Debit or Credit)
            // If it's a Loss (gainLossAmount > 0 for Receipt? No, let's logic it out)
            // Receipt: Pay 150 NGN (1.5) for 140 NGN (1.4) Invoice.
            // Gain of 10 NGN.
            // entry to adjust AR: we credited AR for 150 NGN, but invoice was only 140.
            // So we need to DEBIT AR for 10 NGN and CREDIT Realized Gain for 10 NGN.

            // Disbursement: Pay 150 NGN (1.5) for 140 NGN (1.4) Invoice.
            // Loss of 10 NGN.
            // entry to adjust AP: we debited AP for 150 NGN, but invoice was only 140.
            // So we need to CREDIT AP for 10 NGN and DEBIT Realized Loss for 10 NGN.

            $isReceipt = $payment->payment_direction === 'RECEIPT';
            $adjustAccount = $isReceipt
                ? $party->getReceivablesAccount()
                : $party->getPayablesAccount();

            if ($gainLossAmount > 0) {
                // Realized GAIN (Positive for Receipt, Negative for Disbursement usually, but let's check my formula)
                // My formula in PaymentService: gainLossAmount = appliedLCYPayment - appliedLCYDocument
                // Receipt POSITIVE -> Pay more LCY for same FCY -> GAIN
                // Disbursement POSITIVE -> Pay more LCY for same FCY -> LOSS

                if ($isReceipt) {
                    // GAIN
                    $gainAccount = $currency->realizedGainsAccount;
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $adjustAccount->id,
                        'debit_amount' => $gainLossAmount, // Debit AR (decrease cumulative credit)
                        'credit_amount' => 0,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Gain on {$application->document_number}",
                    ]);
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $gainAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $gainLossAmount, // Credit Gain
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Gain on {$application->document_number}",
                    ]);
                } else {
                    // LOSS (for Disbursement)
                    $lossAccount = $currency->realizedLossesAccount;
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $lossAccount->id,
                        'debit_amount' => $gainLossAmount, // Debit Loss
                        'credit_amount' => 0,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Loss on {$application->document_number}",
                    ]);
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $adjustAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $gainLossAmount, // Credit AP
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Loss on {$application->document_number}",
                    ]);
                }
            } else {
                // Negative -> Realized LOSS for Receipt, GAIN for Disbursement
                $absAmount = abs($gainLossAmount);
                if ($isReceipt) {
                    // LOSS (Receipt)
                    $lossAccount = $currency->realizedLossesAccount;
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $lossAccount->id,
                        'debit_amount' => $absAmount,
                        'credit_amount' => 0,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Loss on {$application->document_number}",
                    ]);
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $adjustAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $absAmount,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Loss on {$application->document_number}",
                    ]);
                } else {
                    // GAIN (Disbursement)
                    $gainAccount = $currency->realizedGainsAccount;
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $adjustAccount->id,
                        'debit_amount' => $absAmount,
                        'credit_amount' => 0,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Gain on {$application->document_number}",
                    ]);
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $gainAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $absAmount,
                        'document_type' => 'PAYMENT',
                        'document_number' => $payment->payment_number,
                        'description' => "Realized Gain on {$application->document_number}",
                    ]);
                }
            }

            return $entries;
        });
    }

    /**
     * Reverse Realized Gain/Loss G/L entries
     */
    public function reverseRealizedGainLoss(PaymentApplication $application): void
    {
        // Simple implementation: Reverse the G/L entries associated with this application
        // In a full BC implementation, we'd look for specific G/L entries by document and source.
        // For now, let's assume we can find them by payment number and description.
        $payment = $application->payment;
        $entries = GlEntry::where('document_number', $payment->payment_number)
            ->where('description', 'like', "%{$application->document_number}%")
            ->get();

        foreach ($entries as $entry) {
            $this->createGlEntry([
                'chart_of_account_id' => $entry->chart_of_account_id,
                'debit_amount' => $entry->credit_amount,
                'credit_amount' => $entry->debit_amount,
                'document_type' => $entry->document_type,
                'document_number' => "REV-{$entry->document_number}",
                'description' => "Reversal: {$entry->description}",
                'source_type' => $entry->source_type,
                'source_number' => $entry->source_number,
            ]);
        }
    }

    public function postPurchaseLine(
        Vendor $vendor,
        Item $item,
        float $quantity,
        float $unitCost,
        float $lineTotal,
        \DateTime $postingDate,
        string $documentNumber,
        string $description,
        float $vatAmount = 0,
        ?int $vatPostingSetupId = null
    ): array {
        $setup = $vendor->getPostingSetupFor($item);

        if (! $setup && $vendor->general_business_posting_group_id && $item->general_product_posting_group_id) {
            $setup = GeneralPostingSetup::query()
                ->where('general_business_posting_group_id', $vendor->general_business_posting_group_id)
                ->where('general_product_posting_group_id', $item->general_product_posting_group_id)
                ->where('blocked', false)
                ->first();
        }

        if (! $setup && $vendor->general_business_posting_group_id) {
            $setup = GeneralPostingSetup::query()
                ->where('general_business_posting_group_id', $vendor->general_business_posting_group_id)
                ->where('blocked', false)
                ->where(function ($query) {
                    $query->whereNotNull('purchase_account_id')
                        ->orWhereNotNull('inventory_account_id');
                })
                ->orderByRaw('CASE WHEN purchase_account_id IS NULL THEN 1 ELSE 0 END, id ASC')
                ->first();
        }

        if (! $setup) {
            $vendorRef = $vendor->vendor_code ?: $vendor->vendor_name ?: (string) $vendor->id;
            throw new \Exception("Posting setup missing for vendor {$vendorRef} and item {$item->item_code}");
        }

        $entries = [];

        // Inventory item
        if ($item->isInventoryItem()) {
            $inventoryAccount = $item->getInventoryAccount();

            if (! $inventoryAccount) {
                throw new \Exception("Inventory account missing for item {$item->item_code}");
            }

            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $inventoryAccount->id,
                'debit_amount' => $lineTotal,
                'credit_amount' => 0,
                'source_type' => 'ITEM',
                'source_number' => $item->item_code,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Inventory receipt: {$description}",
            ]);
        } else {
            $purchaseAccount = $setup->getPurchaseAccount();
            if (! $purchaseAccount) {
                $vendorRef = $vendor->vendor_code ?: $vendor->vendor_name ?: (string) $vendor->id;
                throw new \Exception("Purchase account missing in posting setup for vendor {$vendorRef} and item {$item->item_code}");
            }

            // Expense
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $purchaseAccount->id,
                'debit_amount' => $lineTotal,
                'credit_amount' => 0,
                'source_type' => 'ITEM',
                'source_number' => $item->item_code,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Purchase expense: {$description}",
            ]);
        }

        // VAT Input (Debit)
        if ($vatAmount > 0) {
            $vatSetup = $vatPostingSetupId ? VatPostingSetup::find($vatPostingSetupId) : null;
            if (! $vatSetup) {
                $vatService = app(VatService::class);
                $vatSetup = $vatService->resolveSetup($vendor->vat_business_posting_group_id, $item->vat_product_posting_group_id);
            }

            if ($vatSetup && $vatSetup->purchase_vat_account_id) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $vatSetup->purchase_vat_account_id,
                    'debit_amount' => $vatAmount,
                    'credit_amount' => 0,
                    'document_type' => 'PURCHASE_INVOICE',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => "VAT Input: {$description}",
                ]);
            }
        }

        return $entries;
    }

    public function postFixedAssetPurchase(
        Vendor $vendor,
        FixedAsset $asset,
        float $quantity,
        float $unitCost,
        float $lineTotal,
        \DateTime $postingDate,
        string $documentNumber,
        string $description
    ): array {
        $assetAccount = $asset->postingGroup?->acquisitionCostAccount;

        if (! $assetAccount) {
            throw new \Exception("Asset G/L Account missing for fixed asset {$asset->fa_no}. Please configure Acquisition Cost Account on the FA Posting Group.");
        }

        $entries = [];

        // 1. Debit Fixed Asset G/L Account
        $entries[] = $this->createGlEntry([
            'chart_of_account_id' => $assetAccount->id,
            'debit_amount' => $lineTotal,
            'credit_amount' => 0,
            'source_type' => 'FIXED_ASSET',
            'source_number' => $asset->fa_no,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $documentNumber,
            'posting_date' => $postingDate,
            'description' => "Asset Acquisition: {$description}",
        ]);

        // 2. Update Asset Model
        $asset->acquisition_cost = (float) $asset->acquisition_cost + $lineTotal;
        $asset->book_value = (float) $asset->book_value + $lineTotal;
        if (! $asset->acquisition_date) {
            $asset->acquisition_date = $postingDate;
        }
        $asset->status = FAStatus::ACTIVE;
        $asset->save();

        return $entries;
    }

    public function postVendorPayable(
        Vendor $vendor,
        float $amount,
        \DateTime $postingDate,
        string $documentNumber
    ): array {
        $entries = [];
        $payablesAccount = $vendor->getPayablesAccount();

        if (! $payablesAccount) {
            $vendorCode = $vendor->vendor_code ?: 'N/A';
            $vendorName = $vendor->vendor_name ?: 'N/A';
            $postingGroupId = $vendor->vendor_posting_group_id ?: 'N/A';

            throw new \RuntimeException(
                "No A/P account is configured for vendor '{$vendorName}' ({$vendorCode}). ".
                "Set a Payables Account on Vendor Posting Group ID {$postingGroupId}."
            );
        }

        $entries[] = $this->createGlEntry([
            'chart_of_account_id' => $payablesAccount->id,
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

            if (! $receivablesAccount) {
                throw new \Exception("Customer '{$customer->name}' is missing a receivables account.");
            }

            foreach ($invoice->lines as $line) {

                $item = Item::find($line->item_id);
                if (! $item) {
                    throw new \Exception("Item with ID {$line->item_id} not found.");
                }

                $productGroupId = $item->general_product_posting_group_id;
                $inventoryGroupId = $item->inventory_posting_group_id;
                $itemDisplayName = trim((string) ($item->item_code.' '.$item->description));
                if ($itemDisplayName === '') {
                    $itemDisplayName = "ID {$item->id}";
                }

                // Fetch posting setup for this customer × item group
                $postingSetup = GeneralPostingSetup::where([
                    'general_business_posting_group_id' => $customerGroupId,
                    'general_product_posting_group_id' => $productGroupId,
                ])->first();

                // Fallback: some masters are keyed by inventory group; try that when needed.
                if (! $postingSetup && $inventoryGroupId && $inventoryGroupId !== $productGroupId) {
                    $postingSetup = GeneralPostingSetup::where([
                        'general_business_posting_group_id' => $customerGroupId,
                        'general_product_posting_group_id' => $inventoryGroupId,
                    ])->first();
                }

                // Final fallback: use any active setup for this business group that has sales account configured.
                if (! $postingSetup) {
                    $postingSetup = GeneralPostingSetup::query()
                        ->where('general_business_posting_group_id', $customerGroupId)
                        ->where('blocked', false)
                        ->whereNotNull('sales_account_id')
                        ->orderByRaw('CASE WHEN cogs_account_id IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('id')
                        ->first();
                }

                // In PostingService.php around line 515-520, replace:
                if (! $postingSetup) {
                    throw new \Exception(
                        "No posting setup found for customer '{$customer->name}' (Group: {$customerGroupId}) ".
                        "and item '{$itemDisplayName}' (General Product Group: {$productGroupId}, Inventory Group: {$inventoryGroupId}). ".
                        "Please configure General Posting Setup for Business Group ID {$customerGroupId} ".
                        'with one of those Product Group IDs.'
                    );
                }

                $salesAccount = $postingSetup->getSalesAccount();
                $cogsAccount = $postingSetup->getCogsAccount();
                $inventoryAccount = $item->getInventoryAccount();

                if (! $salesAccount) {
                    throw new \Exception("Missing sales account for item '{$itemDisplayName}'.");
                }

                $vatBusGroup = $invoice->vat_business_posting_group_id;
                $vatProdGroup = $item->vat_product_posting_group_id;

                $vatService = app(VatService::class);
                $vatSetup = $vatService->resolveSetup($vatBusGroup, $vatProdGroup);
                $isPriceInclusive = (bool) ($invoice->is_price_inclusive ?? false);

                $lineTotalRaw = $line->quantity * $line->unit_price;
                $vatCalc = $vatService->calculate($lineTotalRaw, $vatSetup, $isPriceInclusive);

                $lineRevenue = $vatCalc['net_amount'];
                $lineVat = $vatCalc['vat_amount'];
                $lineTotalWithVat = $vatCalc['total_amount'];

                $lineCost = $line->quantity * ($item->unit_cost ?? 0);

                // 1. Accounts Receivable (Debit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $receivablesAccount->id,
                    'debit_amount' => $lineTotalWithVat,
                    'credit_amount' => 0,
                    'document_type' => 'SALES_INVOICE',
                    'document_number' => $invoice->invoice_number,
                    'posting_date' => $invoice->posting_date,
                    'document_date' => $invoice->posting_date,
                    'description' => "Invoice {$invoice->invoice_number} - Total",
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

                // 2.5 VAT Liability (Credit)
                if ($lineVat > 0 && $vatSetup && $vatSetup->sales_vat_account_id) {
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $vatSetup->sales_vat_account_id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineVat,
                        'document_type' => 'SALES_INVOICE',
                        'document_number' => $invoice->invoice_number,
                        'posting_date' => $invoice->posting_date,
                        'document_date' => $invoice->posting_date,
                        'description' => "VAT Output {$item->description}",
                    ]);
                }

                // Inventory postings (only for inventory items)
                if ($item->isInventoryItem()) {

                    if (! $cogsAccount || ! $inventoryAccount) {
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
                    'description' => 'Credit Memo Revenue Reversal',
                ]);

                // 2. A/R Reduction (Credit)
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $memo->customer->getReceivablesAccount()->id,
                    'debit_amount' => 0,
                    'credit_amount' => $lineAmount,
                    'document_type' => 'SALES_CREDIT_MEMO',
                    'document_number' => $memo->memo_number,
                    'posting_date' => $memo->effective_date,
                    'description' => 'Reduce receivable',
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
                        'description' => 'Inventory return',
                    ]);

                    // 4. Reverse COGS (Credit)
                    $entries[] = $this->createGlEntry([
                        'chart_of_account_id' => $setup->getCogsAccount()->id,
                        'debit_amount' => 0,
                        'credit_amount' => $lineCost,
                        'document_type' => 'SALES_CREDIT_MEMO',
                        'document_number' => $memo->memo_number,
                        'posting_date' => $memo->effective_date,
                        'description' => 'Reverse COGS',
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

    public function postFixedAssetDisposal(FixedAsset $asset, float $proceeds, \DateTime $postingDate, string $documentNumber): array
    {
        $postingGroup = $asset->postingGroup;
        if (! $postingGroup) {
            throw new \Exception("Posting group missing for asset {$asset->fa_no}");
        }

        return DB::transaction(function () use ($asset, $proceeds, $postingDate, $documentNumber, $postingGroup) {
            $entries = [];

            // 1. Debit Cash/Bank/Receivable (Proceeds)
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $postingGroup->disposal_proceeds_account_id,
                'debit_amount' => $proceeds,
                'credit_amount' => 0,
                'document_type' => 'FA_DISPOSAL',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Disposal Proceeds: {$asset->description}",
            ]);

            // 2. Reverse Accumulated Depreciation (Debit)
            if ($asset->accumulated_depreciation > 0) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $postingGroup->accumulated_depreciation_account_id,
                    'debit_amount' => (float) $asset->accumulated_depreciation,
                    'credit_amount' => 0,
                    'document_type' => 'FA_DISPOSAL',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => "Reverse Accum. Depr: {$asset->description}",
                ]);
            }

            // 3. Reverse Acquisition Cost (Credit)
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $postingGroup->acquisition_cost_account_id,
                'debit_amount' => 0,
                'credit_amount' => (float) $asset->acquisition_cost,
                'document_type' => 'FA_DISPOSAL',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Reverse Acquisition: {$asset->description}",
            ]);

            // 4. Calculate Gain/Loss
            $bookValue = (float) $asset->acquisition_cost - (float) $asset->accumulated_depreciation;
            $gainLoss = $proceeds - $bookValue;
            $account = $gainLoss >= 0 ? $postingGroup->gain_on_disposal_account_id : $postingGroup->loss_on_disposal_account_id;

            if ($gainLoss != 0 && $account) {
                $entries[] = $this->createGlEntry([
                    'chart_of_account_id' => $account,
                    'debit_amount' => $gainLoss < 0 ? abs($gainLoss) : 0,
                    'credit_amount' => $gainLoss > 0 ? $gainLoss : 0,
                    'document_type' => 'FA_DISPOSAL',
                    'document_number' => $documentNumber,
                    'posting_date' => $postingDate,
                    'description' => ($gainLoss > 0 ? 'Gain' : 'Loss')." on Disposal: {$asset->description}",
                ]);
            }

            // Update Asset Status
            $asset->update([
                'status' => FAStatus::DISPOSED,
                'disposal_date' => $postingDate,
                'disposal_proceeds' => $proceeds,
                'disposal_gain_loss' => $gainLoss,
                'book_value' => 0,
            ]);

            return $entries;
        });
    }

    public function postFixedAssetAppreciation(FixedAsset $asset, float $appreciationAmount, \DateTime $postingDate, string $documentNumber): array
    {
        $postingGroup = $asset->postingGroup;
        if (! $postingGroup || ! $postingGroup->revaluation_account_id) {
            throw new \Exception("Appreciation posting configuration missing for asset {$asset->fa_no}");
        }

        return DB::transaction(function () use ($asset, $appreciationAmount, $postingDate, $documentNumber, $postingGroup) {
            $entries = [];

            // 1. Debit Asset Account
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $postingGroup->acquisition_cost_account_id,
                'debit_amount' => $appreciationAmount,
                'credit_amount' => 0,
                'document_type' => 'FA_APPRECIATION',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Appreciation: {$asset->description}",
            ]);

            // 2. Credit Revaluation Gain/Reserve
            $entries[] = $this->createGlEntry([
                'chart_of_account_id' => $postingGroup->revaluation_account_id,
                'debit_amount' => 0,
                'credit_amount' => $appreciationAmount,
                'document_type' => 'FA_APPRECIATION',
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'description' => "Revaluation Gain: {$asset->description}",
            ]);

            // Update Asset Value
            $asset->update([
                'book_value' => (float) $asset->book_value + $appreciationAmount,
            ]);

            return $entries;
        });
    }

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
