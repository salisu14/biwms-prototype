<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use App\Services\Approval\ApprovalTemplateService;
use App\Services\Purchases\PurchaseCreditMemoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseCreditMemo extends ViewRecord
{
    use ShowsMissingApprovalTemplateWarning;

    protected static string $resource = PurchaseCreditMemoResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Purchase Credit Memo');
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ApprovalActions::all(),
            Action::make('post')
                ->label('Post')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->hidden(fn ($record) => $record->status === ApprovalStatus::POSTED)
                ->disabled(function ($record): bool {
                    if ($record->isPendingApproval()) {
                        return true;
                    }

                    if ($record->status === ApprovalStatus::APPROVED) {
                        return false;
                    }

                    return app(ApprovalTemplateService::class)->requiresApproval($record);
                })
                ->tooltip(function ($record): ?string {
                    if ($record->status === ApprovalStatus::POSTED) {
                        return null;
                    }

                    if ($record->isPendingApproval()) {
                        return 'Post is unavailable while approval is pending.';
                    }

                    if (
                        $record->status !== ApprovalStatus::APPROVED
                        && app(ApprovalTemplateService::class)->requiresApproval($record)
                    ) {
                        return 'Post is available only after the document is approved.';
                    }

                    return null;
                })
                ->action(function ($record) {
                    app(PurchaseCreditMemoService::class)->post($record);

                    Notification::make()
                        ->title('Credit memo posted successfully')
                        ->success()
                        ->send();
                }),
        ];
    }
}
