<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\LeaveRequest;
use Filament\Pages\Page;

class MyLeaveRequestsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.hr.my-leave-requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?string $navigationLabel = 'My Leave Requests';

    protected static ?string $title = 'My Leave Requests';

    protected static ?string $slug = 'my-leave-requests';

    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->employee_id !== null;
    }

    public function getViewData(): array
    {
        return [
            'requests' => LeaveRequest::query()
                ->with('leaveType')
                ->where('employee_id', auth()->user()?->employee_id)
                ->latest()
                ->limit(25)
                ->get(),
        ];
    }
}
