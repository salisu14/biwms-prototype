<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Services\AuditTrailService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeIdCardService
{
    public const ACTIVE_STATUS = 'active';

    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function issueCard(Employee $employee, ?Carbon $issueDate = null, ?Carbon $expiryDate = null): Employee
    {
        return DB::transaction(function () use ($employee, $issueDate, $expiryDate): Employee {
            /** @var Employee $lockedEmployee */
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->getKey());

            $wasIssued = filled($lockedEmployee->id_card_token);
            $issueDate ??= now();
            $expiryDate ??= $issueDate->copy()->addYears(2);

            $lockedEmployee->forceFill([
                'id_card_number' => $lockedEmployee->id_card_number ?: $this->generateCardNumber($lockedEmployee),
                'id_card_issue_date' => $issueDate->toDateString(),
                'id_card_expiry_date' => $expiryDate->toDateString(),
                'id_card_status' => self::ACTIVE_STATUS,
                'id_card_token' => $this->generateToken(),
            ])->save();

            $this->auditTrailService->recordGeneric(
                eventType: 'hr_id_card',
                action: $wasIssued ? 'card_regenerated' : 'card_generated',
                auditable: $lockedEmployee,
                documentType: 'EMPLOYEE_ID_CARD',
                documentNo: $lockedEmployee->id_card_number,
                description: ($wasIssued ? 'Regenerated' : 'Generated')." employee ID card for {$lockedEmployee->employee_number}.",
                metadata: [
                    'employee_number' => $lockedEmployee->employee_number,
                    'id_card_number' => $lockedEmployee->id_card_number,
                    'issue_date' => $lockedEmployee->id_card_issue_date?->toDateString(),
                    'expiry_date' => $lockedEmployee->id_card_expiry_date?->toDateString(),
                ],
            );

            return $lockedEmployee->fresh(['department']);
        });
    }

    public function ensureIssued(Employee $employee): Employee
    {
        if (filled($employee->id_card_number) && filled($employee->id_card_token)) {
            return $employee;
        }

        return $this->issueCard($employee);
    }

    public function companyInformation(): CompanyInformation
    {
        return CompanyInformation::getInstance();
    }

    public function qrPayload(Employee $employee): string
    {
        $payload = $this->payloadWithoutSignature($employee);

        return $payload.'|'.$this->signatureFor($payload);
    }

    public function qrSvg(Employee $employee, int $size = 180): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->qrPayload($employee));
    }

    /**
     * @return array<string, mixed>
     */
    public function cardViewData(Employee $employee): array
    {
        $issuedEmployee = $employee->loadMissing('department');
        $company = $this->companyInformation();

        return [
            'employee' => $issuedEmployee,
            'company' => $company,
            'qrSvg' => $this->qrSvg($issuedEmployee),
            'verificationUrl' => route('employee-card.verify', ['token' => $issuedEmployee->id_card_token]),
            'photoUrl' => $this->publicStorageUrl($issuedEmployee->photo_path),
            'logoUrl' => $company->logo_url,
        ];
    }

    /**
     * @param  iterable<Employee>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function cardViewDataForEmployees(iterable $employees): array
    {
        $cards = [];

        foreach ($employees as $employee) {
            $cards[] = $this->cardViewData($this->ensureIssued($employee));
        }

        return $cards;
    }

    public function isVerifiable(Employee $employee): bool
    {
        if ($employee->id_card_status !== self::ACTIVE_STATUS) {
            return false;
        }

        if ($employee->id_card_expiry_date !== null && $employee->id_card_expiry_date->lt(today())) {
            return false;
        }

        return filled($employee->id_card_token);
    }

    public function signatureFor(string $payloadWithoutSignature): string
    {
        return hash_hmac('sha256', $payloadWithoutSignature, (string) config('app.key'));
    }

    private function payloadWithoutSignature(Employee $employee): string
    {
        return implode('|', [
            (string) $employee->employee_number,
            (string) $employee->id_card_number,
            (string) $employee->id_card_token,
        ]);
    }

    private function generateCardNumber(Employee $employee): string
    {
        $employeeNumber = Str::of((string) $employee->employee_number)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        do {
            $cardNumber = 'ID-'.$employeeNumber.'-'.Str::upper(Str::random(6));
        } while (Employee::query()->where('id_card_number', $cardNumber)->exists());

        return $cardNumber;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Employee::query()->where('id_card_token', $token)->exists());

        return $token;
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::url($path);
    }
}
