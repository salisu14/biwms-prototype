<?php

declare(strict_types=1);

namespace App\Filament\Pages\Hr;

use App\Models\EmployeeAttendanceEvent;
use App\Services\Hr\AttendanceClockService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AttendanceClockPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Clock In / Clock Out';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.hr.attendance-clock-page';

    public ?string $cardToken = null;

    public string $eventType = EmployeeAttendanceEvent::TYPE_CLOCK_IN;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('hr.attendance_clock.use') === true;
    }

    public function clock(AttendanceClockService $clockService): void
    {
        $this->validate([
            'cardToken' => ['required', 'string'],
            'eventType' => ['required', 'in:clock_in,clock_out'],
        ]);

        try {
            $day = $clockService->clockWithCardToken(
                token: (string) $this->cardToken,
                eventType: $this->eventType,
                actor: auth()->user(),
                source: 'hr_clock_page',
            );
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('Attendance rejected')->body($exception->getMessage())->send();

            return;
        }

        $this->cardToken = null;

        Notification::make()
            ->success()
            ->title('Attendance recorded')
            ->body('Daily attendance status: '.str($day->status)->headline())
            ->send();
    }
}
