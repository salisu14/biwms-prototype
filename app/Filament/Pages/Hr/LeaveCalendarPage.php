<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\LeaveRequest;
use Filament\Pages\Page;

class LeaveCalendarPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected string $view = 'filament.pages.hr.leave-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?string $navigationLabel = 'Leave Calendar';

    protected static ?string $title = 'Leave Calendar';

    protected static ?string $slug = 'leave-calendar';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.leave_calendar.view') === true || auth()->user()?->employee_id !== null;
    }

    public function getViewData(): array
    {
        return [
            'requests' => LeaveRequest::query()
                ->with(['employee.department', 'leaveType'])
                ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
                ->whereDate('end_date', '>=', now()->startOfMonth())
                ->whereDate('start_date', '<=', now()->endOfMonth())
                ->orderBy('start_date')
                ->get(),
        ];
    }
}
