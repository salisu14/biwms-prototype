<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentTerm;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\VendorLedgerEntry;
use Illuminate\Support\Facades\Cache;

class PaymentTermsService
{
    private const CACHE_TTL = 3600;

    public function getByCode(string $code): ?PaymentTerm
    {
        return Cache::remember("payment_term.{$code}", self::CACHE_TTL, function () use ($code) {
            return PaymentTerm::where('code', $code)->first();
        });
    }

    /**
     * Apply payment terms to sales order (BC: Copy from Cust.)
     */
    public function applyToSalesOrder(SalesOrder $order, ?string $termsCode = null): void
    {
        $customer = $order->customer;

        $term = $termsCode
            ? $this->getByCode($termsCode)
            : $this->getByCode($customer->payment_terms_code);

        if (! $term || ! $term->canUse()) {
            throw new \InvalidArgumentException('Payment terms not available');
        }

        $postingDate = $order->posting_date ?? now();
        $dueDate = $term->calculateDueDate($postingDate);
        $discountDate = $term->calculateDiscountDate($postingDate);

        $order->update([
            'payment_terms_code' => $term->code,
            'due_date' => $dueDate,
            'payment_discount_percent' => $term->discount_percent,
            'payment_discount_date' => $discountDate,
            'pmt_discount_taken' => 0,
        ]);
    }

    /**
     * Apply payment terms to purchase order
     */
    public function applyToPurchaseOrder(PurchaseOrder $order, ?string $termsCode = null): void
    {
        $vendor = $order->vendor;

        $term = $termsCode
            ? $this->getByCode($termsCode)
            : $this->getByCode($vendor->payment_terms_code);

        if (! $term || ! $term->canUse()) {
            throw new \InvalidArgumentException('Payment terms not available');
        }

        $postingDate = $order->posting_date ?? now();
        $dueDate = $term->calculateDueDate($postingDate);

        $order->update([
            'payment_terms_code' => $term->code,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Calculate payment discount for vendor payment
     */
    public function calculatePaymentDiscount(VendorLedgerEntry $entry, ?\DateTime $paymentDate = null): array
    {
        $term = $this->getByCode($entry->payment_terms_code ?? 'NET30');

        if (! $term || ! $term->discount_allowed) {
            return [
                'eligible' => false,
                'discount_amount' => 0,
                'discount_percent' => 0,
                'payment_amount' => $entry->remaining_amount,
            ];
        }

        $paymentDate = $paymentDate ?? now();
        $postingDate = $entry->posting_date;
        $discountDate = $term->calculateDiscountDate($postingDate);

        $eligible = $paymentDate <= $discountDate;
        $discountAmount = $eligible
            ? $term->calculateDiscount($entry->remaining_amount, $postingDate, $paymentDate)
            : 0;

        return [
            'eligible' => $eligible,
            'discount_date' => $discountDate,
            'payment_date' => $paymentDate,
            'discount_percent' => $eligible ? $term->discount_percent : 0,
            'discount_amount' => $discountAmount,
            'payment_amount' => $entry->remaining_amount - $discountAmount,
            'original_amount' => $entry->remaining_amount,
        ];
    }

    /**
     * Validate payment amount against tolerance
     */
    public function validatePayment(float $paymentAmount, float $expectedAmount, string $termsCode): bool
    {
        $term = $this->getByCode($termsCode);

        if (! $term) {
            return abs($paymentAmount - $expectedAmount) < 0.01;
        }

        return $term->isWithinTolerance($paymentAmount, $expectedAmount);
    }

    /**
     * Seed standard payment terms (BC: Standard Setup)
     */
    public function seedStandardTerms(): void
    {
        $terms = [
            [
                'code' => 'COD',
                'description' => 'Cash on Delivery',
                'calculation_type' => 'cash_receipt',
                'due_date_net_days' => 0,
            ],
            [
                'code' => 'PREPAY',
                'description' => 'Prepayment Required',
                'calculation_type' => 'cash_receipt',
                'due_date_net_days' => 0,
            ],
            [
                'code' => '14D',
                'description' => 'Net 14 Days',
                'calculation_type' => 'net',
                'due_date_net_days' => 14,
            ],
            [
                'code' => '30D',
                'description' => 'Net 30 Days',
                'calculation_type' => 'net',
                'due_date_net_days' => 30,
            ],
            [
                'code' => '60D',
                'description' => 'Net 60 Days',
                'calculation_type' => 'net',
                'due_date_net_days' => 60,
            ],
            [
                'code' => 'EOM',
                'description' => 'End of Month',
                'calculation_type' => 'end_of_month',
                'due_date_net_days' => 0,
            ],
            [
                'code' => 'EOM15',
                'description' => 'End of Month + 15 Days',
                'calculation_type' => 'end_of_month',
                'due_date_net_days' => 15,
            ],
            [
                'code' => '2/10NET30',
                'description' => '2% Discount if Paid in 10 Days, Net 30',
                'calculation_type' => 'net',
                'due_date_net_days' => 30,
                'discount_allowed' => true,
                'discount_percent' => 2,
                'discount_calculation_type' => 'net_days',
                'discount_net_days' => 10,
            ],
            [
                'code' => '1/15NET45',
                'description' => '1% Discount if Paid in 15 Days, Net 45',
                'calculation_type' => 'net',
                'due_date_net_days' => 45,
                'discount_allowed' => true,
                'discount_percent' => 1,
                'discount_calculation_type' => 'net_days',
                'discount_net_days' => 15,
            ],
            [
                'code' => '15TH',
                'description' => 'Due on 15th of Next Month',
                'calculation_type' => 'due_day',
                'due_date_day_of_month' => 15,
                'due_date_months_ahead' => 1,
            ],
        ];

        foreach ($terms as $termData) {
            PaymentTerm::firstOrCreate(
                ['code' => $termData['code']],
                array_merge($termData, ['is_active' => true])
            );
        }
    }
}
