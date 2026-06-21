<?php

namespace App\Notifications;

use App\Models\PurchaseQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteApprovalDelegated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    use Queueable;

    public function __construct(public PurchaseQuote $quote) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Quote {$this->quote->document_no} Delegated to You")
            ->greeting("Hello {$notifiable->name},")
            ->line('A purchase quote has been delegated to you for approval.')
            ->line("Quote: {$this->quote->document_no}")
            ->line("Vendor: {$this->quote->vendor->vendor_name}")
            ->action('Review Quote', url("/purchase-quotes/{$this->quote->id}"));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'quote_id' => $this->quote->id,
            'document_no' => $this->quote->document_no,
            'type' => 'quote_approval_delegated',
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
