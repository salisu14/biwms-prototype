<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionCalculationStatus;
use App\Models\CommissionCalculation;
use App\Models\PostedSalesInvoice;

class CommissionAccrualReadinessService
{
    /**
     * @return array<int, array{classification: string, severity: string, message: string, context: array<string, mixed>}>
     */
    public function findingsForPostedSalesInvoice(PostedSalesInvoice $invoice): array
    {
        $calculation = CommissionCalculation::query()
            ->where('source_type', PostedSalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->latest('id')
            ->first();

        if (! $calculation) {
            return [$this->finding('source_not_calculated', 'warning', 'Posted sales invoice has not been evaluated for commission.', [
                'posted_sales_invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
            ])];
        }

        return match ($calculation->calculation_status) {
            CommissionCalculationStatus::Ineligible => [$this->finding('intentionally_ineligible', 'info', 'Posted sales invoice was evaluated and is not commissionable.', [
                'commission_calculation_id' => $calculation->id,
                'reason' => $calculation->metadata['ineligibility_reason'] ?? null,
            ])],
            CommissionCalculationStatus::Failed => [$this->finding('calculation_failed', 'critical', 'Commission calculation failed and requires retry.', [
                'commission_calculation_id' => $calculation->id,
            ])],
            CommissionCalculationStatus::Pending,
            CommissionCalculationStatus::Calculated,
            CommissionCalculationStatus::PartiallyEligible => [$this->finding('calculation_not_accrued', 'warning', 'Commission calculation has not been accrued.', [
                'commission_calculation_id' => $calculation->id,
                'status' => $calculation->calculation_status->value,
            ])],
            default => [],
        };
    }

    private function finding(string $classification, string $severity, string $message, array $context): array
    {
        return compact('classification', 'severity', 'message', 'context');
    }
}
