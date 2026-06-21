<?php

declare(strict_types=1);

namespace App\Filament\Traits;

use App\Contracts\Approvable;
use App\Services\Approval\ApprovalTemplateService;
use Filament\Notifications\Notification;

trait ShowsMissingApprovalTemplateWarning
{
    protected function warnIfMissingApprovalTemplate(Approvable $record, string $documentLabel): void
    {
        $user = auth()->user();
        if (! $user || ! ($user->hasRole('super_admin') || $user->hasRole('admin'))) {
            return;
        }

        $requiresApproval = app(ApprovalTemplateService::class)->requiresApproval($record);
        if ($requiresApproval) {
            return;
        }

        Notification::make()
            ->title('Approval Template Missing')
            ->body("No matching approval template was found for this {$documentLabel}. It may auto-release.")
            ->warning()
            ->persistent()
            ->send();
    }
}
