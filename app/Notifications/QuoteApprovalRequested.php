<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PurchaseQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PurchaseQuote $quote,
        public ?string $delegatorName = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->delegatorName
            ? "Delegated: Purchase Quote {$this->quote->document_no} Requires Approval"
            : "Purchase Quote {$this->quote->document_no} Requires Your Approval";

        $line = $this->delegatorName
            ? "{$this->delegatorName} has delegated approval of this quote to you."
            : 'A new purchase quote requires your approval.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($line)
            ->line('Quote Details:')
            ->line("Document No: {$this->quote->document_no}")
            ->line("Vendor: {$this->quote->vendor->vendor_name}")
            ->line('Amount: '.number_format($this->quote->amount_including_vat, 2).' '.($this->quote->currency_code ?? 'USD'))
            ->line('Buyer: '.($this->quote->buyer?->name ?? 'N/A'))
            ->action('Review Quote', url("/purchase-quotes/{$this->quote->id}"))
            ->line('Please review and approve or reject this quote.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'quote_id' => $this->quote->id,
            'document_no' => $this->quote->document_no,
            'vendor_name' => $this->quote->vendor->vendor_name,
            'amount' => $this->quote->amount_including_vat,
            'currency' => $this->quote->currency_code,
            'buyer_name' => $this->quote->buyer?->name,
            'type' => 'quote_approval_requested',
            'delegated' => $this->delegatorName !== null,
            'delegator' => $this->delegatorName,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'quote_id' => $this->quote->id,
            'document_no' => $this->quote->document_no,
            'message' => "Quote {$this->quote->document_no} pending approval",
            'amount' => $this->quote->amount_including_vat,
            'type' => 'quote_approval_requested',
        ]);
    }
}
