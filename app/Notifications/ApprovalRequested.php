<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ApprovalEntry $entry
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $model = $this->entry->approvable;
        $documentNo = $this->resolveDocumentNo($model);
        $documentType = $this->resolveDocumentType($model);

        return (new MailMessage)
            ->subject("{$documentType} {$documentNo} Requires Your Approval")
            ->greeting("Hello {$notifiable->name},")
            ->line("A {$documentType} requires your approval.")
            ->line("Document No: {$documentNo}")
            ->line('Amount: ' . number_format($this->resolveAmount($model), 2))
            ->action('Review Document', $this->resolveUrl($model))
            ->line('Please review and approve or reject this document.');
    }

    public function toDatabase(object $notifiable): array
    {
        $model = $this->entry->approvable;

        return [
            'entry_id' => $this->entry->id,
            'approvable_type' => $this->entry->approvable_type,
            'approvable_id' => $this->entry->approvable_id,
            'document_no' => $this->resolveDocumentNo($model),
            'document_type' => $this->resolveDocumentType($model),
            'amount' => $this->resolveAmount($model),
            'type' => 'approval_requested',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $model = $this->entry->approvable;

        return new BroadcastMessage([
            'entry_id' => $this->entry->id,
            'document_no' => $this->resolveDocumentNo($model),
            'message' => $this->resolveDocumentType($model) . ' ' . $this->resolveDocumentNo($model) . ' pending approval',
            'amount' => $this->resolveAmount($model),
            'type' => 'approval_requested',
        ]);
    }

    private function resolveDocumentNo(Model $model): string
    {
        return $model->document_no
            ?? $model->memo_number
            ?? $model->order_number
            ?? $model->number
            ?? (string) $model->getKey();
    }

    private function resolveDocumentType(Model $model): string
    {
        // Build a human-readable type from the class name
        $className = class_basename($model);

        return (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $className);
    }

    private function resolveAmount(Model $model): float
    {
        return (float) ($model->amount_including_vat ?? $model->grand_total ?? $model->total_amount ?? 0);
    }

    private function resolveUrl(Model $model): string
    {
        // Try to build a reasonable URL; Filament resources can override this
        $slug = str(class_basename($model))->kebab()->plural();

        return url("/{$slug}/{$model->getKey()}");
    }
}
