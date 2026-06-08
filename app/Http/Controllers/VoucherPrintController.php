<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PettyCashVoucher;
use Barryvdh\DomPDF\Facade\Pdf;

class VoucherPrintController extends Controller
{
    public function print(PettyCashVoucher $voucher)
    {
        $pdf = Pdf::loadView('pdf.petty-cash-voucher', [
            'voucher' => $voucher->load(['fund', 'lines.expenseAccount', 'lines.department', 'lines.project', 'requestedBy', 'approvedBy', 'postedBy']),
        ]);

        return $pdf->stream("PettyCash-Voucher-{$voucher->voucher_number}.pdf");
    }
}
