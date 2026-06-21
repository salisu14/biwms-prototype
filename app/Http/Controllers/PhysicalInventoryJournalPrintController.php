<?php

namespace App\Http\Controllers;

use App\Models\PhysicalInventoryJournal;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PhysicalInventoryJournalPrintController extends Controller
{
    public function print(
        PhysicalInventoryJournal $journal,
        CompanyInformationService $companyInformationService
    ) {
        $journal->loadMissing([
            'location',
            'bin',
            'reasonCode',
            'assignedUser',
            'countedBy',
            'postedBy',
            'lines.item',
            'lines.location',
            'lines.bin',
            'lines.unitOfMeasure',
        ]);

        $header = $companyInformationService->getReportHeader();

        $data = [
            'journal' => $journal,
            'company' => [
                'name' => $header['name'] ?? config('app.name', 'Bifli WMS'),
                'address_lines' => $header['address_lines'] ?? [],
                'address' => implode(', ', $header['address_lines'] ?? []),
                'email' => $header['email'] ?? null,
                'phone' => $header['phone'] ?? null,
                'website' => $header['website'] ?? null,
                'tax_no' => $header['tax_no'] ?? null,
                'registration_no' => $header['registration_no'] ?? null,
                'logo_url' => $header['logo_url'] ?? null,
            ],
        ];

        $pdf = Pdf::loadView('pdf.physical-inventory-counting-sheet', $data)
            ->setPaper('a4', 'landscape');

        $filename = "PhysicalInventory-{$journal->journal_batch_name}.pdf";
        $fallbackName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename) ?: 'physical-inventory.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, $fallbackName),
            'Content-Length' => strlen($pdf->output()),
        ]);
    }
}
