<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\PayCodeType;
use App\Enums\PayrollStatus;
use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Models\EmployeePayslip;
use App\Models\EmployeePayslipDeduction;
use App\Models\EmployeePayslipEarning;
use App\Models\EmployeePayslipHistory;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Services\AuditTrailService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeePayslipService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly EmployeeIdCardService $employeeIdCardService,
    ) {}

    /**
     * @return Collection<int, EmployeePayslip>
     */
    public function generateForPayrollDocument(PayrollDocument $document): Collection
    {
        $this->assertCanGenerateFromDocument($document);

        $document->loadMissing(['period', 'lines.employee.department', 'lines.payCode']);

        return DB::transaction(function () use ($document): Collection {
            return $document->lines
                ->groupBy('employee_id')
                ->map(fn (Collection $employeeLines): EmployeePayslip => $this->generateFromLineGroup($document, $employeeLines))
                ->values();
        });
    }

    public function generateForPayrollLine(PayrollLine $line): EmployeePayslip
    {
        $line->loadMissing('document');

        return $this->generateForPayrollDocument($line->document)
            ->first(fn (EmployeePayslip $payslip): bool => $payslip->employee_id === $line->employee_id)
            ?? throw new Exception('Unable to generate payslip for payroll line.');
    }

    public function revoke(EmployeePayslip $payslip, ?string $reason = null): EmployeePayslip
    {
        return DB::transaction(function () use ($payslip, $reason): EmployeePayslip {
            /** @var EmployeePayslip $lockedPayslip */
            $lockedPayslip = EmployeePayslip::query()->lockForUpdate()->findOrFail($payslip->id);

            if ($lockedPayslip->isRevoked()) {
                return $lockedPayslip->fresh(['employee', 'earnings', 'deductions']);
            }

            $lockedPayslip->forceFill([
                'status' => EmployeePayslip::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => Auth::id(),
                'revocation_reason' => $reason,
            ])->save();

            $this->recordHistory($lockedPayslip, 'revoked', 'Employee payslip revoked.', ['reason' => $reason]);
            $this->recordAudit($lockedPayslip, 'payslip_revoked', 'Revoked employee payslip.');

            return $lockedPayslip->fresh(['employee', 'earnings', 'deductions']);
        });
    }

    public function regenerate(EmployeePayslip $payslip): EmployeePayslip
    {
        $payslip->loadMissing('payrollDocument');
        $this->assertCanGenerateFromDocument($payslip->payrollDocument);

        return DB::transaction(function () use ($payslip): EmployeePayslip {
            /** @var EmployeePayslip $lockedPayslip */
            $lockedPayslip = EmployeePayslip::query()
                ->with(['payrollDocument.lines.employee.department', 'payrollDocument.lines.payCode'])
                ->lockForUpdate()
                ->findOrFail($payslip->id);

            $employeeLines = $lockedPayslip->payrollDocument->lines
                ->where('employee_id', $lockedPayslip->employee_id)
                ->values();

            $this->refreshPayslipSnapshot($lockedPayslip, $employeeLines);
            $this->recordHistory($lockedPayslip, 'regenerated', 'Employee payslip regenerated from payroll results.');
            $this->recordAudit($lockedPayslip, 'payslip_regenerated', 'Regenerated employee payslip.');

            return $lockedPayslip->fresh(['employee', 'payrollPeriod', 'payrollDocument', 'earnings', 'deductions']);
        });
    }

    public function markDownloaded(EmployeePayslip $payslip): EmployeePayslip
    {
        $payslip->forceFill(['download_count' => $payslip->download_count + 1])->save();
        $this->recordHistory($payslip, 'downloaded', 'Employee payslip downloaded.');
        $this->recordAudit($payslip, 'payslip_downloaded', 'Downloaded employee payslip.');

        return $payslip->fresh(['employee', 'earnings', 'deductions']);
    }

    public function markPreviewed(EmployeePayslip $payslip): EmployeePayslip
    {
        $this->recordHistory($payslip, 'previewed', 'Employee payslip previewed.');
        $this->recordAudit($payslip, 'payslip_previewed', 'Previewed employee payslip.');

        return $payslip->fresh(['employee', 'earnings', 'deductions']);
    }

    public function markPrinted(EmployeePayslip $payslip): EmployeePayslip
    {
        $payslip->forceFill(['printed_at' => now()])->save();
        $this->recordHistory($payslip, 'printed', 'Employee payslip printed.');
        $this->recordAudit($payslip, 'payslip_printed', 'Printed employee payslip.');

        return $payslip->fresh(['employee', 'earnings', 'deductions']);
    }

    /**
     * @return array<string, mixed>
     */
    public function payslipViewData(EmployeePayslip $payslip, bool $forPdf = false): array
    {
        $payslip->loadMissing(['earnings', 'deductions', 'employee.department', 'payrollPeriod']);
        $company = CompanyInformation::getInstance($payslip->business_id);

        return [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'company' => $company,
            'logoSrc' => $forPdf
                ? $this->employeeIdCardService->resolveCompanyLogoForPdf($company)
                : $company->logo_url,
            'print' => false,
        ];
    }

    private function generateFromLineGroup(PayrollDocument $document, Collection $employeeLines): EmployeePayslip
    {
        $firstLine = $employeeLines->first();
        if (! $firstLine instanceof PayrollLine) {
            throw new Exception('Cannot generate a payslip without payroll lines.');
        }

        /** @var EmployeePayslip|null $existing */
        $existing = EmployeePayslip::query()
            ->where('employee_id', $firstLine->employee_id)
            ->where('payroll_period_id', $document->payroll_period_id)
            ->where('payroll_document_id', $document->id)
            ->first();

        if ($existing) {
            return $existing->fresh(['employee', 'payrollPeriod', 'payrollDocument', 'earnings', 'deductions']);
        }

        $payslip = EmployeePayslip::query()->create([
            'employee_id' => $firstLine->employee_id,
            'payroll_period_id' => $document->payroll_period_id,
            'payroll_document_id' => $document->id,
            'payroll_line_id' => $firstLine->id,
            'payslip_number' => $this->generatePayslipNumber($document, $firstLine->employee),
            'verification_token' => Str::random(64),
            'generated_by' => Auth::id(),
            'generated_at' => now(),
        ]);

        $this->refreshPayslipSnapshot($payslip, $employeeLines);
        $this->recordHistory($payslip, 'generated', 'Employee payslip generated from payroll results.');
        $this->recordAudit($payslip, 'payslip_generated', 'Generated employee payslip.');

        return $payslip->fresh(['employee', 'payrollPeriod', 'payrollDocument', 'earnings', 'deductions']);
    }

    private function refreshPayslipSnapshot(EmployeePayslip $payslip, Collection $employeeLines): void
    {
        $firstLine = $employeeLines->first();
        if (! $firstLine instanceof PayrollLine) {
            throw new Exception('Cannot refresh a payslip without payroll lines.');
        }

        $document = $firstLine->document()->with('period')->firstOrFail();
        $employee = $firstLine->employee()->with('department')->firstOrFail();
        $company = CompanyInformation::getInstance();

        $earnings = $employeeLines->filter(fn (PayrollLine $line): bool => $this->isLineType($line, PayCodeType::EARNING));
        $deductions = $employeeLines->filter(fn (PayrollLine $line): bool => $this->isLineType($line, PayCodeType::DEDUCTION));
        $grossEarnings = round((float) $earnings->sum(fn (PayrollLine $line): float => (float) $line->amount), 4);
        $totalDeductions = round((float) $deductions->sum(fn (PayrollLine $line): float => (float) $line->amount), 4);
        $netPay = round($grossEarnings - $totalDeductions, 4);

        if (round($grossEarnings - $totalDeductions, 2) !== round($netPay, 2)) {
            throw new Exception('Payslip net pay validation failed.');
        }

        $payslip->forceFill([
            'status' => EmployeePayslip::STATUS_GENERATED,
            'currency_code' => $company->base_currency_code ?? 'NGN',
            'payment_date' => $document->period?->payment_date ?? $document->period_end,
            'worked_days' => 0,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'amount_in_words' => $this->amountInWords($netPay, $company->base_currency_code ?? 'NGN'),
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->full_name,
            'employee_email' => $employee->email,
            'employee_phone' => $employee->phone,
            'department_name' => $employee->department?->name ?? $employee->department_code,
            'job_title' => $employee->job_title,
            'company_name' => $company->company_name,
            'company_address' => CompanyInformation::getFullAddress(),
            'company_phone' => $company->phone_no ?? $company->mobile_no,
            'company_email' => $company->email,
            'company_logo_path' => $company->logo_path,
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
        ])->save();

        $payslip->earnings()->delete();
        $payslip->deductions()->delete();

        $this->snapshotLines($payslip, $earnings, EmployeePayslipEarning::class);
        $this->snapshotLines($payslip, $deductions, EmployeePayslipDeduction::class);
    }

    /**
     * @param  class-string<EmployeePayslipEarning|EmployeePayslipDeduction>  $modelClass
     */
    private function snapshotLines(EmployeePayslip $payslip, Collection $lines, string $modelClass): void
    {
        $displayOrder = 1;

        foreach ($lines as $line) {
            /** @var PayrollLine $line */
            $modelClass::query()->create([
                'payslip_id' => $payslip->id,
                'pay_code_id' => $line->pay_code_id,
                'pay_code' => $line->payCode?->code,
                'description' => $line->description ?: $line->payCode?->name ?: $line->line_type,
                'quantity' => $line->hours ?: 1,
                'rate' => $line->rate ?: $line->amount,
                'amount' => $line->amount,
                'display_order' => $displayOrder++,
            ]);
        }
    }

    private function assertCanGenerateFromDocument(?PayrollDocument $document): void
    {
        if (! $document) {
            throw new Exception('Payroll document is required for payslip generation.');
        }

        if (! $document->payroll_period_id) {
            throw new Exception('Payroll document must be linked to a payroll period before payslips can be generated.');
        }

        if (! in_array($document->status, [PayrollStatus::APPROVED, PayrollStatus::POSTED], true)) {
            throw new Exception('Payslips can only be generated from approved or posted payroll documents.');
        }

        if (! $document->lines()->exists()) {
            throw new Exception('Payroll document has no employee payroll results.');
        }
    }

    private function isLineType(PayrollLine $line, PayCodeType $type): bool
    {
        return $line->line_type === $type->getLabel()
            || $line->payCode?->type === $type;
    }

    private function generatePayslipNumber(PayrollDocument $document, Employee $employee): string
    {
        $employeeNumber = Str::of((string) $employee->employee_number)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        do {
            $number = 'PS-'.$document->document_number.'-'.$employeeNumber.'-'.Str::upper(Str::random(4));
        } while (EmployeePayslip::query()->where('payslip_number', $number)->exists());

        return $number;
    }

    private function amountInWords(float $amount, string $currencyCode): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
            $words = $formatter->format($amount);

            if (is_string($words)) {
                return Str::title($words).' '.$currencyCode.' Only';
            }
        }

        return number_format($amount, 2).' '.$currencyCode.' Only';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordHistory(EmployeePayslip $payslip, string $event, string $description, array $metadata = []): void
    {
        EmployeePayslipHistory::query()->create([
            'payslip_id' => $payslip->id,
            'employee_id' => $payslip->employee_id,
            'actor_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function recordAudit(EmployeePayslip $payslip, string $action, string $description): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'hr_payslip',
            action: $action,
            auditable: $payslip,
            documentType: 'EMPLOYEE_PAYSLIP',
            documentNo: $payslip->payslip_number,
            description: $description,
            metadata: [
                'employee_id' => $payslip->employee_id,
                'employee_number' => $payslip->employee_number,
                'payroll_document_id' => $payslip->payroll_document_id,
                'payroll_period_id' => $payslip->payroll_period_id,
                'status' => $payslip->status,
            ],
        );
    }
}
