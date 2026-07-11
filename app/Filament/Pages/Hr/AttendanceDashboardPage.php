<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\EmployeeAttendanceDay;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AttendanceDashboardPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Dashboard';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.hr.attendance-dashboard-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.attendance_report.view') === true
            || auth()->user()?->can('hr.attendance_register.view_any') === true;
    }

    public function getViewData(): array
    {
        $today = today();

        return [
            'presentToday' => EmployeeAttendanceDay::query()->whereDate('attendance_date', $today)->whereIn('status', ['present', 'late'])->count(),
            'lateToday' => EmployeeAttendanceDay::query()->whereDate('attendance_date', $today)->where('late_minutes', '>', 0)->count(),
            'missingClockOutToday' => EmployeeAttendanceDay::query()->whereDate('attendance_date', $today)->where('missing_clock_out', true)->count(),
            'payrollReviewCount' => EmployeeAttendanceDay::query()->where('payroll_review_required', true)->count(),
        ];
    }
}
