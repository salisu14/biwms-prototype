<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Hr\AttendanceCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveHoliday extends Model
{
    /**
     * @var array<int|string, mixed>
     */
    private static array $attendanceOriginalDates = [];

    protected $fillable = [
        'business_id',
        'name',
        'holiday_date',
        'is_active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (LeaveHoliday $holiday): void {
            if ($holiday->isDirty(['holiday_date', 'is_active'])) {
                self::$attendanceOriginalDates[$holiday->getKey()] = $holiday->getOriginal('holiday_date');
            }
        });

        static::saved(function (LeaveHoliday $holiday): void {
            self::recalculateAttendanceForDate($holiday->holiday_date);

            $previousDate = self::$attendanceOriginalDates[$holiday->getKey()] ?? null;
            unset(self::$attendanceOriginalDates[$holiday->getKey()]);

            if ($previousDate !== null) {
                self::recalculateAttendanceForDate($previousDate);
            }
        });

        static::deleted(function (LeaveHoliday $holiday): void {
            self::recalculateAttendanceForDate($holiday->holiday_date);
        });
    }

    private static function recalculateAttendanceForDate(mixed $date): void
    {
        if ($date === null) {
            return;
        }

        EmployeeAttendanceDay::query()
            ->with('employee')
            ->whereDate('attendance_date', $date)
            ->chunkById(200, function ($days): void {
                foreach ($days as $day) {
                    app(AttendanceCalculationService::class)->recalculate($day->employee, $day->attendance_date);
                }
            });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
