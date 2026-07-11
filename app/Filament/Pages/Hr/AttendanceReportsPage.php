<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\EmployeeAttendanceDay;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AttendanceReportsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Reports';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.hr.attendance-reports-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.attendance_report.view') === true;
    }

    public function getViewData(): array
    {
        $from = today()->startOfMonth();
        $until = today()->endOfMonth();

        return [
            'from' => $from,
            'until' => $until,
            'workedHours' => round(EmployeeAttendanceDay::query()->whereBetween('attendance_date', [$from, $until])->sum('worked_minutes') / 60, 2),
            'lateMinutes' => (int) EmployeeAttendanceDay::query()->whereBetween('attendance_date', [$from, $until])->sum('late_minutes'),
            'overtimeMinutes' => (int) EmployeeAttendanceDay::query()->whereBetween('attendance_date', [$from, $until])->sum('overtime_minutes'),
            'payrollReviewCount' => EmployeeAttendanceDay::query()->whereBetween('attendance_date', [$from, $until])->where('payroll_review_required', true)->count(),
        ];
    }
}
