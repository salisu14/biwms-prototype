<?php

declare(strict_types=1);

namespace App\Filament\Shared\Actions;

use App\Contracts\Approvable;
use App\Contracts\ApprovableStatus;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ApprovalActions
{
    /**
     * "Send Approval Request" button — visible when the status allows submission.
     */
    public static function makeSendApprovalRequestAction(): Action
    {
        return Action::make('sendApprovalRequest')
            ->label('Send Approval Request')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(function ($record): bool {
                if (! $record instanceof Approvable) {
                    return false;
                }

                $status = $record->status;

                return $status instanceof ApprovableStatus && $status->canSubmitForApproval();
            })
            ->requiresConfirmation()
            ->action(function (Approvable $record, ApprovalService $service) {
                try {
                    $service->submitForApproval($record);
                    Notification::make()
                        ->title('Approval request sent')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error sending approval request')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * "Cancel Approval Request" button — visible when there are pending approval entries.
     */
    public static function makeCancelApprovalRequestAction(): Action
    {
        return Action::make('cancelApprovalRequest')
            ->label('Cancel Approval Request')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->visible(function ($record): bool {
                if (! $record instanceof Approvable) {
                    return false;
                }

                return $record->isPendingApproval();
            })
            ->requiresConfirmation()
            ->action(function (Approvable $record, ApprovalService $service) {
                $service->cancelRequest($record);
                Notification::make()
                    ->title('Approval request cancelled')
                    ->success()
                    ->send();
            });
    }

    /**
     * "Approve" button — visible to the assigned approver (non super-admin must be the assigned approver).
     */
    public static function makeApproveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(function ($record): bool {
                if (! $record instanceof Approvable) {
                    return false;
                }

                $entry = $record->currentApprovalEntry;
                if (! $entry) {
                    return false;
                }

                $user = Auth::user();

                return $entry->approver_id === $user?->id || $user?->hasRole('super_admin');
            })
            ->form([
                Textarea::make('comment')
                    ->label('Comment')
                    ->placeholder('Optional comment...'),
            ])
            ->action(function (Approvable $record, array $data, ApprovalService $service) {
                $entry = $record->currentApprovalEntry;
                if ($entry) {
                    $service->approve($entry, $data['comment'] ?? null);
                    Notification::make()
                        ->title('Document approved')
                        ->success()
                        ->send();
                }
            });
    }

    /**
     * "Reject" button — visible to the assigned approver.
     */
    public static function makeRejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(function ($record): bool {
                if (! $record instanceof Approvable) {
                    return false;
                }

                $entry = $record->currentApprovalEntry;
                if (! $entry) {
                    return false;
                }

                $user = Auth::user();

                return $entry->approver_id === $user?->id || $user?->hasRole('super_admin');
            })
            ->form([
                Textarea::make('reason')
                    ->label('Reason for Rejection')
                    ->required(),
            ])
            ->action(function (Approvable $record, array $data, ApprovalService $service) {
                $entry = $record->currentApprovalEntry;
                if ($entry) {
                    $service->reject($entry, $data['reason']);
                    Notification::make()
                        ->title('Document rejected')
                        ->warning()
                        ->send();
                }
            });
    }

    /**
     * "Delegate" button — visible to the assigned approver if delegation is allowed.
     */
    public static function makeDelegateAction(): Action
    {
        return Action::make('delegate')
            ->label('Delegate')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->visible(function ($record): bool {
                if (! $record instanceof Approvable) {
                    return false;
                }

                $entry = $record->currentApprovalEntry;

                return $entry && $entry->approver_id === Auth::id();
            })
            ->form([
                Select::make('delegatee_id')
                    ->label('Delegate To')
                    ->options(
                        User::whereNot('id', Auth::id())
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Approvable $record, array $data, ApprovalService $service) {
                $entry = $record->currentApprovalEntry;
                $delegatee = User::find($data['delegatee_id']);
                if ($entry && $delegatee) {
                    $service->delegate($entry, $delegatee);
                    Notification::make()
                        ->title('Approval delegated to ' . $delegatee->name)
                        ->success()
                        ->send();
                }
            });
    }

    /**
     * Convenience: return all standard approval header actions at once.
     *
     * @return array<Action>
     */
    public static function all(): array
    {
        return [
            self::makeSendApprovalRequestAction(),
            self::makeCancelApprovalRequestAction(),
            self::makeApproveAction(),
            self::makeRejectAction(),
            self::makeDelegateAction(),
        ];
    }
}
