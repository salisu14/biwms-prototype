<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PurchaseQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseQuote $quote,
        public string $approverName
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Quote {$this->quote->document_no} Approved")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your purchase quote has been approved by {$this->approverName}.")
            ->line("Quote: {$this->quote->document_no}")
            ->line("Vendor: {$this->quote->vendor->vendor_name}")
            ->action('View Quote', url("/purchase-quotes/{$this->quote->id}"))
            ->line('The quote is now released and ready for conversion to order.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'quote_id' => $this->quote->id,
            'document_no' => $this->quote->document_no,
            'approver' => $this->approverName,
            'type' => 'quote_approved',
        ];
    }
}
