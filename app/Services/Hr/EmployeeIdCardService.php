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
use Illuminate\Support\Facades\File;
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
    public function cardViewData(Employee $employee, bool $forPdf = false): array
    {
        $issuedEmployee = $employee->loadMissing('department');
        $company = $this->companyInformation();
        $photoUrl = $this->publicStorageUrl($issuedEmployee->photo_path);
        $logoUrl = $company->logo_url;

        return [
            'employee' => $issuedEmployee,
            'company' => $company,
            'qrSvg' => $this->qrSvg($issuedEmployee),
            'verificationUrl' => route('employee-card.verify', ['token' => $issuedEmployee->id_card_token]),
            'photoUrl' => $photoUrl,
            'logoUrl' => $logoUrl,
            'photoSrc' => $forPdf ? $this->resolveEmployeePhotoForPdf($issuedEmployee) : $photoUrl,
            'logoSrc' => $forPdf ? $this->resolveCompanyLogoForPdf($company) : $logoUrl,
        ];
    }

    /**
     * @param  iterable<Employee>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function cardViewDataForEmployees(iterable $employees, bool $forPdf = false): array
    {
        $cards = [];

        foreach ($employees as $employee) {
            $cards[] = $this->cardViewData($this->ensureIssued($employee), $forPdf);
        }

        return $cards;
    }

    public function resolveEmployeePhotoForPdf(Employee $employee): ?string
    {
        return $this->imageDataUriFromStoragePath($employee->photo_path, ['public']);
    }

    public function resolveCompanyLogoForPdf(?CompanyInformation $company = null): ?string
    {
        $company ??= $this->companyInformation();

        $logoPaths = collect([
            $company->logo_path,
            CompanyInformation::query()
                ->whereNull('business_id')
                ->whereNotNull('logo_path')
                ->value('logo_path'),
            CompanyInformation::query()
                ->whereNotNull('logo_path')
                ->value('logo_path'),
        ])->filter();

        foreach ($logoPaths as $logoPath) {
            $dataUri = $this->imageDataUriFromStoragePath((string) $logoPath, ['public']);

            if ($dataUri !== null) {
                return $dataUri;
            }
        }

        return null;
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

        $normalizedPath = $this->normalizeStoragePath($path);

        return $normalizedPath !== null ? Storage::disk('public')->url($normalizedPath) : null;
    }

    /**
     * @param  array<int, string>  $preferredDisks
     */
    private function imageDataUriFromStoragePath(?string $path, array $preferredDisks = []): ?string
    {
        $normalizedPath = $this->normalizeStoragePath($path);
        if ($normalizedPath === null) {
            return null;
        }

        foreach ($this->storageDisks($preferredDisks) as $disk) {
            $absolutePath = $this->safeAbsolutePathForDisk($disk, $normalizedPath);
            if ($absolutePath === null || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
                continue;
            }

            $mimeType = File::mimeType($absolutePath);
            if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }

            $contents = file_get_contents($absolutePath);
            if ($contents === false) {
                continue;
            }

            return 'data:'.$mimeType.';base64,'.base64_encode($contents);
        }

        return null;
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim((string) $path));

        if ($path === '' || Str::startsWith($path, ['http://', 'https://', '/', '../', './'])) {
            return null;
        }

        $path = preg_replace('#^storage/#', '', $path) ?? $path;
        $path = preg_replace('#^public/#', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<int, string>  $preferredDisks
     * @return array<int, string>
     */
    private function storageDisks(array $preferredDisks): array
    {
        return collect([
            ...$preferredDisks,
            config('filesystems.default'),
            'public',
        ])
            ->filter(fn (mixed $disk): bool => is_string($disk) && $disk !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function safeAbsolutePathForDisk(string $disk, string $path): ?string
    {
        $diskConfig = config("filesystems.disks.{$disk}");
        if (($diskConfig['driver'] ?? null) !== 'local') {
            return null;
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }

            $root = realpath(Storage::disk($disk)->path(''));
            $absolutePath = realpath(Storage::disk($disk)->path($path));
            $rootPrefix = $root !== false ? rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR : false;

            if ($rootPrefix === false || $absolutePath === false || ! Str::startsWith($absolutePath, $rootPrefix)) {
                return null;
            }

            return $absolutePath;
        } catch (\Throwable) {
            return null;
        }
    }
}
