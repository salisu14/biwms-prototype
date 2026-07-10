<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\LeaveRequest;
use Filament\Pages\Page;

class LeaveApprovalsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected string $view = 'filament.pages.hr.leave-approvals';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?string $navigationLabel = 'Leave Approvals';

    protected static ?string $title = 'Leave Approvals';

    protected static ?string $slug = 'leave-approvals';

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.leave_approval.approve') === true || auth()->user()?->employee_id !== null;
    }

    public function getViewData(): array
    {
        $user = auth()->user();

        return [
            'requests' => LeaveRequest::query()
                ->with(['employee.department', 'leaveType'])
                ->whereIn('status', [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_MANAGER_APPROVED])
                ->when(! $user?->can('hr.leave_approval.approve'), function ($query) use ($user): void {
                    $query->whereHas('employee.department', fn ($departmentQuery) => $departmentQuery->where('manager_id', $user?->employee_id));
                })
                ->latest()
                ->limit(50)
                ->get(),
        ];
    }
}
