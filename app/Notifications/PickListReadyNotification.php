<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WarehouseActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PickListReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public WarehouseActivity $activity
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'activity_no' => $this->activity->no,
            'type' => $this->activity->activity_type->value,
            'location' => $this->activity->location->code,
            'lines_count' => $this->activity->lines->count(),
            'source_document' => $this->activity->source_document,
            'source_no' => $this->activity->source_no,
        ];
    }
}
