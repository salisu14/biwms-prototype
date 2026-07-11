<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AttendanceLedgerEntry;
use App\Models\EmployeeAttendanceEvent;
use App\Services\Hr\AttendanceClockService;
use App\Services\Hr\EmployeeIdCardService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class MyAttendance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'filament.pages.my-attendance';

    protected static ?string $navigationLabel = 'My Attendance';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'My Attendance';

    protected static ?string $slug = 'my-attendance';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()?->employee_id !== null;
    }

    public function clockIn(AttendanceClockService $clockService, EmployeeIdCardService $cardService): void
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            Notification::make()->danger()->title('No linked employee')->body('Your account must be linked to an employee profile.')->send();

            return;
        }

        try {
            $card = $cardService->ensureIssued($employee);
            $clockService->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_IN, actor: auth()->user(), source: 'my_attendance');
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('Clock-in rejected')->body($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Clocked in')->body('Your start time has been recorded.')->send();
    }

    public function clockOut(AttendanceClockService $clockService, EmployeeIdCardService $cardService): void
    {
        $employee = auth()->user()?->employee;

        if (! $employee) {
            Notification::make()->danger()->title('No linked employee')->body('Your account must be linked to an employee profile.')->send();

            return;
        }

        try {
            $card = $cardService->ensureIssued($employee);
            $clockService->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_OUT, actor: auth()->user(), source: 'my_attendance');
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('Clock-out rejected')->body($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Clocked out')->body('Your end time has been recorded.')->send();
    }

    public function getViewData(): array
    {
        $employeeId = auth()->user()?->employee_id;
        $todayEntry = null;
        $recentEntries = collect();

        if ($employeeId) {
            $todayEntry = AttendanceLedgerEntry::query()
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', Carbon::today())
                ->first();

            $recentEntries = AttendanceLedgerEntry::query()
                ->where('employee_id', $employeeId)
                ->latest('attendance_date')
                ->limit(14)
                ->get();
        }

        return [
            'todayEntry' => $todayEntry, // <-- This was missing!
            'recentEntries' => $recentEntries,
        ];
    }
}
