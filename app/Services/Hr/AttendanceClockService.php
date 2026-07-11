<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLocation;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCard;
use App\Models\User;
use App\Services\AuditTrailService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceClockService
{
    public function __construct(
        private readonly EmployeeIdCardService $employeeIdCardService,
        private readonly AttendanceCalculationService $attendanceCalculationService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function clockWithCardToken(
        string $token,
        ?string $eventType = null,
        CarbonInterface|string|null $occurredAt = null,
        ?User $actor = null,
        ?AttendanceDevice $device = null,
        ?AttendanceLocation $location = null,
        string $source = 'qr',
    ): EmployeeAttendanceDay {
        $card = $this->employeeIdCardService->verifyCardToken($token);

        if (! $card instanceof EmployeeIdCard || ! $card->employee || ! $card->employee->is_active) {
            throw new \RuntimeException('Employee ID card is invalid, expired, revoked, or replaced.');
        }

        $occurredAt = $occurredAt === null ? now() : Carbon::parse($occurredAt);
        $eventType ??= $this->nextEventType($card->employee_id, $occurredAt);
        $attendanceDate = $this->attendanceDateFor($card->employee_id, $eventType, $occurredAt);

        return DB::transaction(function () use ($token, $card, $eventType, $occurredAt, $actor, $device, $location, $source, $attendanceDate): EmployeeAttendanceDay {
            Employee::query()->lockForUpdate()->findOrFail($card->employee_id);
            $this->guardDuplicateRapidScan($card->employee_id, $eventType, $occurredAt);

            $event = EmployeeAttendanceEvent::query()->create([
                'employee_id' => $card->employee_id,
                'employee_id_card_id' => $card->id,
                'attendance_device_id' => $device?->id,
                'attendance_location_id' => $location?->id,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
                'attendance_date' => $attendanceDate,
                'source' => $source,
                'card_token_hash' => $this->tokenHash($token),
                'verification_result' => 'active',
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_by' => $actor?->id ?? auth()->id(),
                'metadata' => [
                    'card_number' => $card->card_number,
                    'device_code' => $device?->code,
                    'location_code' => $location?->code,
                ],
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'attendance',
                action: $eventType,
                auditable: $event,
                userId: $actor?->id ?? auth()->id(),
                description: 'Employee attendance event recorded.',
                metadata: [
                    'employee_id' => $card->employee_id,
                    'attendance_date' => $attendanceDate->toDateString(),
                    'source' => $source,
                ],
            );

            return $this->attendanceCalculationService->recalculate($card->employee, $attendanceDate);
        });
    }

    private function nextEventType(int $employeeId, CarbonInterface $occurredAt): string
    {
        $latestEvent = EmployeeAttendanceEvent::query()
            ->where('employee_id', $employeeId)
            ->where('occurred_at', '>=', Carbon::parse($occurredAt)->copy()->subHours(18))
            ->latest('occurred_at')
            ->first();

        return $latestEvent?->event_type === EmployeeAttendanceEvent::TYPE_CLOCK_IN
            ? EmployeeAttendanceEvent::TYPE_CLOCK_OUT
            : EmployeeAttendanceEvent::TYPE_CLOCK_IN;
    }

    private function attendanceDateFor(int $employeeId, string $eventType, CarbonInterface $occurredAt): Carbon
    {
        if ($eventType === EmployeeAttendanceEvent::TYPE_CLOCK_OUT) {
            $openClockIn = EmployeeAttendanceEvent::query()
                ->where('employee_id', $employeeId)
                ->whereIn('event_type', [EmployeeAttendanceEvent::TYPE_CLOCK_IN, EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_IN])
                ->where('occurred_at', '>=', Carbon::parse($occurredAt)->copy()->subHours(18))
                ->latest('occurred_at')
                ->first();

            if ($openClockIn !== null) {
                return $openClockIn->attendance_date->copy()->startOfDay();
            }
        }

        return Carbon::parse($occurredAt)->startOfDay();
    }

    private function guardDuplicateRapidScan(int $employeeId, string $eventType, CarbonInterface $occurredAt): void
    {
        $duplicateWindowMinutes = (int) config('hr.attendance_duplicate_scan_window_minutes', 2);
        $occurredAt = Carbon::parse($occurredAt);

        $duplicateExists = EmployeeAttendanceEvent::query()
            ->where('employee_id', $employeeId)
            ->where('event_type', $eventType)
            ->whereBetween('occurred_at', [
                $occurredAt->copy()->subMinutes($duplicateWindowMinutes),
                $occurredAt->copy()->addMinutes($duplicateWindowMinutes),
            ])
            ->exists();

        if ($duplicateExists) {
            throw new \RuntimeException('Duplicate rapid attendance scan detected. Please wait before scanning again.');
        }
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
