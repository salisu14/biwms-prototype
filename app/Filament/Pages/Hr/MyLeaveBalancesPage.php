<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\EmployeeLeaveLedgerEntry;
use Filament\Pages\Page;

class MyLeaveBalancesPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected string $view = 'filament.pages.hr.my-leave-balances';

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?string $navigationLabel = 'My Leave Balances';

    protected static ?string $title = 'My Leave Balances';

    protected static ?string $slug = 'my-leave-balances';

    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        return auth()->user()?->employee_id !== null;
    }

    public function getViewData(): array
    {
        return [
            'balances' => EmployeeLeaveLedgerEntry::query()
                ->with('leaveType')
                ->where('employee_id', auth()->user()?->employee_id)
                ->selectRaw('leave_type_id, leave_year, SUM(quantity) as balance')
                ->groupBy('leave_type_id', 'leave_year')
                ->orderByDesc('leave_year')
                ->get(),
        ];
    }
}
