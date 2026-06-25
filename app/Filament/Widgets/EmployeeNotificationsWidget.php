<?php

namespace App\Filament\Widgets;

use App\Notifications\EmployeeAssignedNotification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class EmployeeNotificationsWidget extends Widget
{
    protected string $view = 'filament.widgets.employee-notifications-widget';

    protected int|string|array $columnSpan = 'full';

    public function getNotifications()
    {
        return Auth::user()
            ->unreadNotifications
            ->where('type', EmployeeAssignedNotification::class)
            ->take(10);
    }
}
