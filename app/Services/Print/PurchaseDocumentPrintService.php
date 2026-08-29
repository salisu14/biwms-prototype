<?php

declare(strict_types=1);

namespace App\Services\Print;

use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PurchaseReceipt;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseDocumentPrintService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function generatePurchaseReceipt(PurchaseReceipt $receipt)
    {
        $receipt->loadMissing(['lines.item', 'vendor', 'purchaseOrder', 'receivingLocation', 'postedByUser']);

        return Pdf::loadView('pdf.purchase-receipt', [
            'receipt' => $receipt,
            'company' => $this->companyPayload($receipt->business_id ?? $receipt->purchaseOrder?->business_id),
        ])->setPaper('a4', 'portrait');
    }

    public function generatePurchaseReceiptThermal80mm(PurchaseReceipt $receipt)
    {
        $receipt->loadMissing(['lines.item', 'vendor', 'purchaseOrder', 'receivingLocation', 'postedByUser']);

        return Pdf::loadView('pdf.purchase-receipt-thermal', [
            'receipt' => $receipt,
            'company' => $this->companyPayload($receipt->business_id ?? $receipt->purchaseOrder?->business_id),
            'format' => '80mm',
        ])->setPaper($this->thermalPaperSize('80mm', max(1, $receipt->lines->count())), 'portrait');
    }

    public function generatePurchaseReceiptThermal58mm(PurchaseReceipt $receipt)
    {
        $receipt->loadMissing(['lines.item', 'vendor', 'purchaseOrder', 'receivingLocation', 'postedByUser']);

        return Pdf::loadView('pdf.purchase-receipt-thermal', [
            'receipt' => $receipt,
            'company' => $this->companyPayload($receipt->business_id ?? $receipt->purchaseOrder?->business_id),
            'format' => '58mm',
        ])->setPaper($this->thermalPaperSize('58mm', max(1, $receipt->lines->count())), 'portrait');
    }

    public function generatePostedPurchaseCreditMemo(PostedPurchaseCreditMemo $memo)
    {
        $memo->loadMissing(['lines', 'vendor', 'correctedInvoice', 'location', 'poster']);

        return Pdf::loadView('pdf.posted-purchase-credit-memo', [
            'memo' => $memo,
            'company' => $this->companyPayload($memo->business_id ?? $memo->correctedInvoice?->business_id),
        ])->setPaper('a4', 'portrait');
    }

    public function generateVendorPaymentReceipt(Payment $payment)
    {
        return $this->renderVendorPaymentReceipt($payment, 'a4', 'pdf.vendor-payment-receipt', 'a4');
    }

    public function generateVendorPaymentReceipt80mm(Payment $payment)
    {
        return $this->renderVendorPaymentReceipt($payment, '80mm', 'pdf.vendor-payment-receipt-thermal', '80mm');
    }

    public function generateVendorPaymentReceipt58mm(Payment $payment)
    {
        return $this->renderVendorPaymentReceipt($payment, '58mm', 'pdf.vendor-payment-receipt-thermal', '58mm');
    }

    private function renderVendorPaymentReceipt(Payment $payment, string $paperLabel, string $view, string $format)
    {
        $header = $this->companyPayload($payment->business_id);

        $applications = PaymentApplication::query()
            ->active()
            ->where('payment_id', $payment->id)
            ->latest('applied_at')
            ->get()
            ->map(fn (PaymentApplication $application): array => [
                'applied_at' => optional($application->applied_at)->format('d/m/Y H:i'),
                'document_number' => $application->document_number,
                'document_type' => $application->document_type,
                'amount_applied' => (float) $application->amount_applied,
                'remaining_after' => (float) $application->document_remaining_after,
            ]);

        return Pdf::loadView($view, [
            'payment' => $payment,
            'applications' => $applications,
            'company' => $header,
            'format' => $format,
        ])->setPaper($this->thermalPaperSize($paperLabel, max(1, $applications->count())), 'portrait');
    }

    private function companyPayload(?int $businessId = null): array
    {
        $resolvedBusinessId = $businessId ?? session('active_business_id');
        $header = $this->companyInformationService->getReportHeader($resolvedBusinessId);

        return [
            'name' => $header['name'] ?? config('app.name', 'Bifli WMS'),
            'address_lines' => $header['address_lines'] ?? [],
            'email' => $header['email'] ?? null,
            'phone' => $header['phone'] ?? null,
            'website' => $header['website'] ?? null,
            'tax_no' => $header['tax_no'] ?? null,
            'logo_data_uri' => $header['logo_data_uri'] ?? null,
            'invoice_footer' => $this->companyInformationService->getInvoiceFooter($resolvedBusinessId),
        ];
    }

    private function thermalPaperSize(string $format, int $contentCount): array
    {
        $widthMm = match ($format) {
            '58mm' => 58,
            default => 80,
        };

        $heightMm = match ($format) {
            '58mm' => max(220, 130 + ($contentCount * 36)),
            default => max(260, 150 + ($contentCount * 32)),
        };

        return [
            0,
            0,
            $widthMm * 2.8346456693,
            $heightMm * 2.8346456693,
        ];
    }
}
