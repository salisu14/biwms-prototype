<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeIdCardHistory;
use App\Models\EmployeeIdCardPrintBatch;
use App\Models\EmployeeIdCardPrintBatchItem;
use App\Models\EmployeeIdCardTemplate;
use App\Models\EmployeeIdCardVerificationLog;
use App\Services\AuditTrailService;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeIdCardService
{
    public const ACTIVE_STATUS = EmployeeIdCard::STATUS_ACTIVE;

    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function issueCard(Employee $employee, ?Carbon $issueDate = null, ?Carbon $expiryDate = null, ?EmployeeIdCardTemplate $template = null): EmployeeIdCard
    {
        return DB::transaction(function () use ($employee, $issueDate, $expiryDate, $template): EmployeeIdCard {
            /** @var Employee $lockedEmployee */
            $lockedEmployee = Employee::query()
                ->with('activeIdCard')
                ->lockForUpdate()
                ->findOrFail($employee->getKey());

            if ($lockedEmployee->activeIdCard) {
                return $lockedEmployee->activeIdCard;
            }

            $issueDate ??= now();
            $expiryDate ??= $issueDate->copy()->addYears(2);
            $template ??= $this->defaultTemplate();

            $card = EmployeeIdCard::query()->create([
                'employee_id' => $lockedEmployee->id,
                'business_id' => null,
                'template_id' => $template?->id,
                'card_number' => $lockedEmployee->id_card_number ?: $this->generateCardNumber($lockedEmployee),
                'token' => $lockedEmployee->id_card_token ?: $this->generateToken(),
                'status' => EmployeeIdCard::STATUS_ACTIVE,
                'issue_date' => $issueDate->toDateString(),
                'expiry_date' => $expiryDate->toDateString(),
                'issued_by' => Auth::id(),
                'issued_at' => now(),
            ]);

            $this->mirrorCardToEmployee($lockedEmployee, $card);
            $this->recordHistory($card, 'issued', 'Employee ID card issued.');
            $this->recordAudit($card, 'card_generated', 'Generated employee ID card.');

            return $card->fresh(['employee.department', 'template']);
        });
    }

    public function ensureIssued(Employee $employee): EmployeeIdCard
    {
        return $this->activeCardForEmployee($employee) ?? $this->issueCard($employee);
    }

    public function activeCardForEmployee(Employee $employee): ?EmployeeIdCard
    {
        return EmployeeIdCard::query()
            ->with(['employee.department', 'template'])
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeIdCard::STATUS_ACTIVE)
            ->latest('issued_at')
            ->latest('id')
            ->first();
    }

    public function replaceCard(EmployeeIdCard|Employee $cardOrEmployee, ?string $reason = null): EmployeeIdCard
    {
        return DB::transaction(function () use ($cardOrEmployee, $reason): EmployeeIdCard {
            $oldCard = $cardOrEmployee instanceof EmployeeIdCard
                ? $cardOrEmployee->loadMissing('employee')
                : $this->ensureIssued($cardOrEmployee);

            /** @var EmployeeIdCard $lockedOldCard */
            $lockedOldCard = EmployeeIdCard::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($oldCard->id);

            if ($lockedOldCard->status === EmployeeIdCard::STATUS_ACTIVE) {
                $lockedOldCard->forceFill([
                    'status' => EmployeeIdCard::STATUS_REPLACED,
                    'revocation_reason' => $reason,
                ])->save();

                $this->recordHistory($lockedOldCard, 'replaced', 'Employee ID card replaced.', ['reason' => $reason]);
            }

            $newCard = EmployeeIdCard::query()->create([
                'employee_id' => $lockedOldCard->employee_id,
                'business_id' => $lockedOldCard->business_id,
                'template_id' => $lockedOldCard->template_id ?: $this->defaultTemplate()?->id,
                'card_number' => $this->generateCardNumber($lockedOldCard->employee),
                'token' => $this->generateToken(),
                'status' => EmployeeIdCard::STATUS_ACTIVE,
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addYears(2)->toDateString(),
                'issued_by' => Auth::id(),
                'issued_at' => now(),
                'replaced_card_id' => $lockedOldCard->id,
            ]);

            $this->mirrorCardToEmployee($lockedOldCard->employee, $newCard);
            $this->recordHistory($newCard, 'issued', 'Replacement employee ID card issued.', ['replaced_card_id' => $lockedOldCard->id]);
            $this->recordAudit($newCard, 'card_regenerated', 'Regenerated employee ID card.');

            return $newCard->fresh(['employee.department', 'template', 'replacedCard']);
        });
    }

    public function revokeCard(EmployeeIdCard $card, ?string $reason = null): EmployeeIdCard
    {
        return DB::transaction(function () use ($card, $reason): EmployeeIdCard {
            /** @var EmployeeIdCard $lockedCard */
            $lockedCard = EmployeeIdCard::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($card->id);

            $lockedCard->forceFill([
                'status' => EmployeeIdCard::STATUS_REVOKED,
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ])->save();

            if ($lockedCard->employee) {
                $lockedCard->employee->forceFill([
                    'id_card_status' => EmployeeIdCard::STATUS_REVOKED,
                ])->save();
            }

            $this->recordHistory($lockedCard, 'revoked', 'Employee ID card revoked.', ['reason' => $reason]);
            $this->recordAudit($lockedCard, 'card_revoked', 'Revoked employee ID card.');

            return $lockedCard->fresh(['employee.department', 'template']);
        });
    }

    public function markLost(EmployeeIdCard $card, ?string $reason = null): EmployeeIdCard
    {
        $card->forceFill([
            'status' => EmployeeIdCard::STATUS_LOST,
            'revoked_by' => Auth::id(),
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ])->save();

        $this->recordHistory($card, 'lost', 'Employee ID card marked as lost.', ['reason' => $reason]);

        return $card->fresh(['employee.department', 'template']);
    }

    public function markPrinted(EmployeeIdCard $card): EmployeeIdCard
    {
        $card->forceFill([
            'printed_by' => Auth::id(),
            'printed_at' => now(),
            'print_count' => $card->print_count + 1,
        ])->save();

        $this->recordHistory($card, 'printed', 'Employee ID card printed.');

        return $card->fresh(['employee.department', 'template']);
    }

    /**
     * @param  iterable<EmployeeIdCard>  $cards
     */
    public function createPrintBatch(iterable $cards, string $layout = 'single', ?EmployeeIdCardTemplate $template = null): EmployeeIdCardPrintBatch
    {
        return DB::transaction(function () use ($cards, $layout, $template): EmployeeIdCardPrintBatch {
            $template ??= $this->defaultTemplate();
            $batch = EmployeeIdCardPrintBatch::query()->create([
                'template_id' => $template?->id,
                'batch_number' => $this->generateBatchNumber(),
                'layout' => $layout,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            foreach ($cards as $card) {
                EmployeeIdCardPrintBatchItem::query()->create([
                    'batch_id' => $batch->id,
                    'card_id' => $card->id,
                    'employee_id' => $card->employee_id,
                    'status' => 'pending',
                ]);
            }

            return $batch->fresh(['items.card.employee']);
        });
    }

    public function cardForToken(?string $token): ?EmployeeIdCard
    {
        if (blank($token)) {
            return null;
        }

        return EmployeeIdCard::query()
            ->with(['employee.department', 'template'])
            ->where('token', $token)
            ->first();
    }

    public function verifyCardToken(string $token): ?EmployeeIdCard
    {
        $card = $this->cardForToken($token);
        $result = $this->isVerifiable($card) ? 'active' : 'invalid';

        EmployeeIdCardVerificationLog::query()->create([
            'card_id' => $card?->id,
            'verified_at' => now(),
            'result' => $result,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        if (! $this->isVerifiable($card)) {
            return null;
        }

        $card->forceFill(['last_verified_at' => now()])->save();
        $this->recordHistory($card, 'verified', 'Employee ID card verified.');

        return $card->fresh(['employee.department', 'template']);
    }

    public function companyInformation(): CompanyInformation
    {
        return CompanyInformation::getInstance();
    }

    public function qrPayload(EmployeeIdCard|Employee $cardOrEmployee): string
    {
        $card = $cardOrEmployee instanceof EmployeeIdCard
            ? $cardOrEmployee
            : $this->ensureIssued($cardOrEmployee);

        $payload = implode('|', [
            (string) $card->employee?->employee_number,
            (string) $card->card_number,
            (string) $card->token,
        ]);

        return $payload.'|'.$this->signatureFor($payload);
    }

    public function qrSvg(EmployeeIdCard|Employee $cardOrEmployee, int $size = 180): string
    {
        return $this->renderQrSvg($this->qrPayload($cardOrEmployee), $size);
    }

    public function renderQrSvg(string $payload, int $size = 180): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload);
    }

    public function renderQrPngDataUri(string $payload, int $size = 180): ?string
    {
        try {
            $png = (new Writer(new GDLibRenderer($size)))->writeString($payload);
        } catch (\Throwable) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @return array<string, mixed>
     */
    public function cardViewData(EmployeeIdCard|Employee $cardOrEmployee, bool $forPdf = false): array
    {
        $card = $cardOrEmployee instanceof EmployeeIdCard
            ? $cardOrEmployee->loadMissing(['employee.department', 'template'])
            : $this->ensureIssued($cardOrEmployee)->loadMissing(['employee.department', 'template']);

        $employee = $card->employee;
        $company = $this->companyInformation();
        $photoUrl = $this->publicStorageUrl($employee?->photo_path);
        $logoUrl = $company->logo_url;
        $qrPayload = $this->qrPayload($card);

        return [
            'card' => $card,
            'employee' => $employee,
            'company' => $company,
            'qrSvg' => $this->renderQrSvg($qrPayload),
            'verificationUrl' => route('employee-card.verify', ['token' => $card->token]),
            'photoUrl' => $photoUrl,
            'logoUrl' => $logoUrl,
            'photoSrc' => $forPdf ? $this->resolveEmployeePhotoForPdf($employee) : $photoUrl,
            'logoSrc' => $forPdf ? $this->resolveCompanyLogoForPdf($company) : $logoUrl,
            ...($forPdf ? ['qrPdfSrc' => $this->renderQrPngDataUri($qrPayload)] : []),
        ];
    }

    /**
     * @param  iterable<EmployeeIdCard|Employee>  $cards
     * @return array<int, array<string, mixed>>
     */
    public function cardViewDataForEmployees(iterable $cards, bool $forPdf = false): array
    {
        $viewData = [];

        foreach ($cards as $card) {
            $viewData[] = $this->cardViewData($card, $forPdf);
        }

        return $viewData;
    }

    public function resolveEmployeePhotoForPdf(?Employee $employee): ?string
    {
        return $this->imageDataUriFromStoragePath($employee?->photo_path, ['public']);
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

    public function isVerifiable(EmployeeIdCard|Employee|null $cardOrEmployee): bool
    {
        if ($cardOrEmployee === null) {
            return false;
        }

        $card = $cardOrEmployee instanceof EmployeeIdCard
            ? $cardOrEmployee
            : $this->activeCardForEmployee($cardOrEmployee);

        return $card?->isActive() === true
            && $card->employee?->is_active === true;
    }

    public function signatureFor(string $payloadWithoutSignature): string
    {
        return hash_hmac('sha256', $payloadWithoutSignature, (string) config('app.key'));
    }

    private function mirrorCardToEmployee(Employee $employee, EmployeeIdCard $card): void
    {
        $employee->forceFill([
            'id_card_number' => $card->card_number,
            'id_card_issue_date' => $card->issue_date,
            'id_card_expiry_date' => $card->expiry_date,
            'id_card_status' => $card->status,
            'id_card_token' => $card->token,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordHistory(EmployeeIdCard $card, string $event, string $description, array $metadata = []): void
    {
        EmployeeIdCardHistory::query()->create([
            'card_id' => $card->id,
            'employee_id' => $card->employee_id,
            'actor_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function recordAudit(EmployeeIdCard $card, string $action, string $description): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'hr_id_card',
            action: $action,
            auditable: $card,
            documentType: 'EMPLOYEE_ID_CARD',
            documentNo: $card->card_number,
            description: $description,
            metadata: [
                'employee_id' => $card->employee_id,
                'employee_number' => $card->employee?->employee_number,
                'card_number' => $card->card_number,
                'status' => $card->status,
            ],
        );
    }

    private function defaultTemplate(): ?EmployeeIdCardTemplate
    {
        return EmployeeIdCardTemplate::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
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
        } while (EmployeeIdCard::query()->where('card_number', $cardNumber)->exists());

        return $cardNumber;
    }

    private function generateBatchNumber(): string
    {
        do {
            $batchNumber = 'IDB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (EmployeeIdCardPrintBatch::query()->where('batch_number', $batchNumber)->exists());

        return $batchNumber;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (EmployeeIdCard::query()->where('token', $token)->exists());

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
