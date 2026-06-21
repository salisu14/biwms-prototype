<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BinContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBinStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BinContent $binContent,
        public float $threshold
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low Stock Alert: {$this->binContent->item->item_no}")
            ->line("Item {$this->binContent->item->item_no} in bin {$this->binContent->bin->bin_code}")
            ->line("Current quantity: {$this->binContent->quantity}")
            ->line("Below threshold: {$this->threshold}")
            ->action('View Bin', url("/warehouse/bins/{$this->binContent->bin_id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'bin_id' => $this->binContent->bin_id,
            'item_id' => $this->binContent->item_id,
            'current_qty' => $this->binContent->quantity,
            'threshold' => $this->threshold,
            'message' => "Low stock in bin {$this->binContent->bin->bin_code}",
        ];
    }
}
