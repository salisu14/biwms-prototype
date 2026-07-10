<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayslip;
use App\Services\Hr\EmployeePayslipService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class EmployeePayslipController extends Controller
{
    public function __construct(
        private readonly EmployeePayslipService $payslipService,
    ) {}

    public function preview(int $payslipId): Response
    {
        $payslip = $this->findPayslip($payslipId);
        abort_unless(Gate::allows('view', $payslip), 403);
        $payslip = $this->payslipService->markPreviewed($payslip);

        return response()->view('hr.employee-payslip', [
            'payslips' => [$this->payslipService->payslipViewData($payslip)],
            'print' => false,
        ]);
    }

    public function print(int $payslipId): Response
    {
        $payslip = $this->findPayslip($payslipId);
        abort_unless(Gate::allows('print', $payslip), 403);
        $payslip = $this->payslipService->markPrinted($payslip);

        return response()->view('hr.employee-payslip', [
            'payslips' => [$this->payslipService->payslipViewData($payslip)],
            'print' => true,
        ]);
    }

    public function download(int $payslipId)
    {
        $payslip = $this->findPayslip($payslipId);
        abort_unless(Gate::allows('download', $payslip), 403);
        $payslip = $this->payslipService->markDownloaded($payslip);

        return Pdf::loadView('hr.employee-payslip', [
            'payslips' => [$this->payslipService->payslipViewData($payslip, forPdf: true)],
            'print' => false,
        ])
            ->setPaper('a4', 'portrait')
            ->download($this->fileName($payslip));
    }

    public function bulkDownload()
    {
        abort_unless(auth()->user()?->can('hr.employee_payslip.download'), 403);

        $ids = collect(explode(',', (string) request('ids')))
            ->filter()
            ->map(fn (string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        /** @var Collection<int, EmployeePayslip> $payslips */
        $payslips = EmployeePayslip::query()
            ->with(['employee', 'earnings', 'deductions', 'payrollPeriod'])
            ->whereKey($ids)
            ->orderBy('employee_number')
            ->get();

        abort_if($payslips->isEmpty(), 404);

        $data = $payslips
            ->map(function (EmployeePayslip $payslip): array {
                abort_unless(Gate::allows('download', $payslip), 403);
                $payslip = $this->payslipService->markDownloaded($payslip);

                return $this->payslipService->payslipViewData($payslip, forPdf: true);
            })
            ->all();

        return Pdf::loadView('hr.employee-payslip', [
            'payslips' => $data,
            'print' => false,
        ])
            ->setPaper('a4', 'portrait')
            ->download('employee-payslips.pdf');
    }

    private function fileName(EmployeePayslip $payslip): string
    {
        return str($payslip->payslip_number ?: 'employee-payslip')
            ->slug()
            ->append('.pdf')
            ->toString();
    }

    private function findPayslip(int $payslipId): EmployeePayslip
    {
        return EmployeePayslip::query()
            ->with(['employee', 'earnings', 'deductions', 'payrollPeriod'])
            ->findOrFail($payslipId);
    }
}
