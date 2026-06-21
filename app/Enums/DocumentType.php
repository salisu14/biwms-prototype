<?php

// app/Enums/DocumentType.php

namespace App\Enums;

enum DocumentType: string
{
    case PAYMENT = 'PAYMENT';
    case INVOICE = 'INVOICE';
    case RECEIPT = 'RECEIPT';
    case CREDIT_NOTE = 'CREDIT_NOTE';
    case DEBIT_NOTE = 'DEBIT_NOTE';
    case REFUNDED_PAYMENT = 'REFUNDED_PAYMENT';
    case REFUNDED_INVOICE = 'REFUNDED_INVOICE';
    case FINANCE_CHARGE = 'FINANCE_CHARGE';
    case CREDIT_MEMO = 'CREDIT_MEMO';
    case DEBIT_MEMO = 'DEBIT_MEMO';
    case CASH_RECEIPT = 'CASH_RECEIPT';
    case QUOTE = 'QUOTE';
    case ORDER = 'ORDER';
    case BILL_OF_LADING = 'BILL_OF_LADING';
    case CERTIFICATE_OF_ORIGIN = 'CERTIFICATE_OF_ORIGIN';
    case CERTIFICATE_OF_DELIVERY = 'CERTIFICATE_OF_DELIVERY';
    case CERTIFICATE_OF_ORIGIN_AND_DELIVERY = 'CERTIFICATE_OF_ORIGIN_AND_DELIVERY';
    case PURCHASE_ORDER = 'PURCHASE_ORDER';
    case PRODUCTION_ORDER = 'PRODUCTION_ORDER';
    case SALES_ORDER = 'SALES_ORDER';
    case TRANSFER_ORDER = 'TRANSFER_ORDER';
    case ADJUSTMENT = 'ADJUSTMENT';
    case RETURN = 'RETURN';
    case SCRAP = 'SCRAP';

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'Purchase Order',
            self::PRODUCTION_ORDER => 'Production Order',
            self::SALES_ORDER => 'Sales Order',
            self::TRANSFER_ORDER => 'Transfer Order',
            self::ADJUSTMENT => 'Inventory Adjustment',
            self::RETURN => 'Customer Return',
            self::SCRAP => 'Scrap/Waste',
            self::PAYMENT => 'Payment',
            self::INVOICE => 'Invoice',
            self::RECEIPT => 'Receipt',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
            self::REFUNDED_PAYMENT => 'Refunded Payment',
            self::REFUNDED_INVOICE => 'Refunded Invoice',
            self::FINANCE_CHARGE => 'Finance Charge',
            self::CREDIT_MEMO => 'Credit Memo',
            self::DEBIT_MEMO => 'Debit Memo',
            self::CASH_RECEIPT => 'Cash Receipt',
            self::QUOTE => 'Quote',
            self::ORDER => 'Order',
            self::BILL_OF_LADING => 'Bill of Lading',
            self::CERTIFICATE_OF_ORIGIN => 'Certificate of Origin',
            self::CERTIFICATE_OF_DELIVERY => 'Certificate of Delivery',
            self::CERTIFICATE_OF_ORIGIN_AND_DELIVERY => 'Certificate of Origin and Delivery',
        };
    }

    /**
     * Color for UI badges (Tailwind classes)
     */
    public function color(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'bg-blue-100 text-blue-800 border-blue-200',
            self::PRODUCTION_ORDER => 'bg-purple-100 text-purple-800 border-purple-200',
            self::SALES_ORDER => 'bg-green-100 text-green-800 border-green-200',
            self::TRANSFER_ORDER => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            self::ADJUSTMENT => 'bg-orange-100 text-orange-800 border-orange-200',
            self::RETURN => 'bg-red-100 text-red-800 border-red-200',
            self::SCRAP => 'bg-gray-100 text-gray-800 border-gray-200',
            self::PAYMENT => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            self::INVOICE => 'bg-teal-100 text-teal-800 border-teal-200',
            self::RECEIPT => 'bg-pink-100 text-pink-800 border-pink-200',
            self::CREDIT_NOTE => 'bg-rose-100 text-rose-800 border-rose-200',
            self::DEBIT_NOTE => 'bg-lime-100 text-lime-800 border-lime-200',
            self::REFUNDED_PAYMENT => 'bg-cyan-100 text-cyan-800 border-cyan-200',
            self::REFUNDED_INVOICE => 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200',
            self::FINANCE_CHARGE => 'bg-amber-100 text-amber-800 border-amber-200',
            self::CREDIT_MEMO => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::DEBIT_MEMO => 'bg-violet-100 text-violet-800 border-violet-200',
            self::CASH_RECEIPT => 'bg-sky-100 text-sky-800 border-sky-200',
            self::QUOTE => 'bg-zinc-100 text-zinc-800 border-zinc-200',
            self::ORDER => 'bg-neutral-100 text-neutral-800 border-neutral-200',
            self::BILL_OF_LADING => 'bg-stone-100 text-stone-800 border-stone-200',
            self::CERTIFICATE_OF_ORIGIN => 'bg-slate-100 text-slate-800 border-slate-200',
            self::CERTIFICATE_OF_DELIVERY => 'bg-neutral-100 text-neutral-800 border-neutral-200',
            self::CERTIFICATE_OF_ORIGIN_AND_DELIVERY => 'bg-neutral-100 text-neutral-800 border-neutral-200',
        };
    }

    /**
     * Icon for UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'shopping-cart',
            self::PRODUCTION_ORDER => 'factory',
            self::SALES_ORDER => 'truck',
            self::TRANSFER_ORDER => 'exchange-alt',
            self::ADJUSTMENT => 'balance-scale',
            self::RETURN => 'undo',
            self::SCRAP => 'trash',
            self::PAYMENT => 'credit-card',
            self::INVOICE => 'file-invoice',
            self::RECEIPT => 'file-invoice-dollar',
            self::CREDIT_NOTE => 'file-invoice-dollar',
            self::DEBIT_NOTE => 'file-invoice-dollar',
            self::REFUNDED_PAYMENT => 'credit-card',
            self::REFUNDED_INVOICE => 'file-invoice',
            self::FINANCE_CHARGE => 'money-bill-wave',
            self::CREDIT_MEMO => 'file-invoice-dollar',
            self::DEBIT_MEMO => 'file-invoice-dollar',
            self::CASH_RECEIPT => 'money-bill-wave',
            self::QUOTE => 'file-invoice-dollar',
            self::ORDER => 'file-invoice-dollar',
            self::BILL_OF_LADING => 'file-invoice-dollar',
            self::CERTIFICATE_OF_ORIGIN => 'file-invoice-dollar',
            self::CERTIFICATE_OF_DELIVERY => 'file-invoice-dollar',
            self::CERTIFICATE_OF_ORIGIN_AND_DELIVERY => 'file-invoice-dollar',
            default => 'file-invoice-dollar',
        };
    }

    /**
     * Whether this document type affects financials
     */
    public function isFinancial(): bool
    {
        return in_array($this, [
            self::PURCHASE_ORDER,
            self::SALES_ORDER,
            self::ADJUSTMENT,
        ]);
    }

    /**
     * Get all values for select dropdowns
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
            ])
            ->toArray();
    }
}
