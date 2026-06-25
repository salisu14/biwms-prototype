<?php

namespace App\Notifications;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class EmployeeAssignedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Employee $employee,
        public Department $department,
        public string $action = 'assigned', // assigned, removed, changed
        public ?Department $previousDepartment = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = match($this->action) {
            'assigned' => "New Employee Assigned: {$this->employee->full_name}",
            'removed' => "Employee Removed: {$this->employee->full_name}",
            'changed' => "Employee Transfer: {$this->employee->full_name}",
            default => "Employee Assignment Update",
        };

        $greeting = "Hello {$notifiable->first_name},";

        $introLines = match($this->action) {
            'assigned' => [
                "A new employee has been assigned to your department **{$this->department->name}**.",
                "**Employee Details:**",
                "- Name: {$this->employee->full_name}",
                "- Employee Number: {$this->employee->employee_number}",
                "- Job Title: {$this->employee->job_title}",
                "- Email: {$this->employee->email}",
            ],
            'removed' => [
                "An employee has been removed from your department **{$this->department->name}**.",
                "**Employee Details:**",
                "- Name: {$this->employee->full_name}",
                "- Employee Number: {$this->employee->employee_number}",
            ],
            'changed' => [
                "An employee has been transferred departments.",
                "**Employee:** {$this->employee->full_name} ({$this->employee->employee_number})",
                "**From:** {$this->previousDepartment?->name}",
                "**To:** {$this->department->name}",
            ],
            default => ["An employee assignment has been updated."]
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->lines($introLines)
            ->line('Please review this change in the administration panel.')
            ->action(
                'View Employee',
                url("/admin/employees/{$this->employee->id}/edit")
            )
            ->line('If you did not make this change, please contact your system administrator.')
            ->salutation('Regards, ' . config('app.name') . ' System');
    }

    /**
     * Get the array representation of the notification stored in database.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'employee_number' => $this->employee->employee_number,
            'department_id' => $this->department->id,
            'department_name' => $this->department->name,
            'previous_department_id' => $this->previousDepartment?->id,
            'previous_department_name' => $this->previousDepartment?->name,
            'action' => $this->action,
            'message' => $this->getMessageText(),
            'icon' => $this->getIcon(),
            'url' => url("/admin/employees/{$this->employee->id}/edit"),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'department_name' => $this->department->name,
            'action' => $this->action,
            'message' => $this->getMessageText(),
            'icon' => $this->getIcon(),
            'url' => url("/admin/employees/{$this->employee->id}/edit"),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get human-readable message text.
     */
    protected function getMessageText(): string
    {
        return match($this->action) {
            'assigned' => "{$this->employee->full_name} was assigned to {$this->department->name}",
            'removed' => "{$this->employee->full_name} was removed from {$this->department->name}",
            'changed' => "{$this->employee->full_name} transferred to {$this->department->name}",
            default => "Employee assignment updated for {$this->employee->full_name}"
        };
    }

    /**
     * Get icon based on action type.
     */
    protected function getIcon(): string
    {
        return match($this->action) {
            'assigned' => 'heroicon-o-user-plus',
            'removed' => 'heroicon-o-user-minus',
            'changed' => 'heroicon-o-arrow-right',
            default => 'heroicon-o-user-group'
        };
    }
}
