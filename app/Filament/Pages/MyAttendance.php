<?php

namespace App\Filament\Pages;

use App\Models\AttendanceLedgerEntry;
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

    public function clockIn(): void
    {
        $employeeId = auth()->user()?->employee_id;

        if (! $employeeId) {
            Notification::make()
                ->danger()
                ->title('No linked employee')
                ->body('Your account must be linked to an employee profile before attendance can be tracked.')
                ->send();

            return;
        }

        $today = Carbon::today();

        $entry = AttendanceLedgerEntry::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'attendance_date' => $today],
            ['created_by' => auth()->id(), 'status' => 'OPEN']
        );

        if ($entry->clock_in_at) {
            Notification::make()
                ->warning()
                ->title('Already clocked in')
                ->body('You have already clocked in for today.')
                ->send();

            return;
        }

        $entry->update(['clock_in_at' => now()]);

        Notification::make()
            ->success()
            ->title('Clocked in')
            ->body('Your start time has been recorded.')
            ->send();
    }

    public function clockOut(): void
    {
        $employeeId = auth()->user()?->employee_id;

        if (! $employeeId) {
            Notification::make()
                ->danger()
                ->title('No linked employee')
                ->body('Your account must be linked to an employee profile before attendance can be tracked.')
                ->send();

            return;
        }

        $entry = AttendanceLedgerEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', Carbon::today())
            ->first();

        if (! $entry || ! $entry->clock_in_at) {
            Notification::make()
                ->warning()
                ->title('Clock in first')
                ->body('No active attendance session found for today.')
                ->send();

            return;
        }

        if ($entry->clock_out_at) {
            Notification::make()
                ->warning()
                ->title('Already clocked out')
                ->body('Your end time for today is already recorded.')
                ->send();

            return;
        }

        if ($entry->status !== 'OPEN') {
            Notification::make()
                ->warning()
                ->title('Cannot edit attendance')
                ->body('This attendance entry is already finalized by HR.')
                ->send();

            return;
        }

        $entry->update(['clock_out_at' => now()]);

        Notification::make()
            ->success()
            ->title('Clocked out')
            ->body('Your end time has been recorded.')
            ->send();
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
            'todayEntry' => $todayEntry,
            'recentEntries' => $recentEntries,
        ];
    }
}
