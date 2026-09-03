<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubledgerOpeningBalances\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\SubledgerOpeningBalance;
use App\Services\Finance\SubledgerOpeningBalanceService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

final class ViewSubledgerOpeningBalance extends ViewRecord
{
    protected static string $resource = SubledgerOpeningBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewParty')
                ->label(fn (SubledgerOpeningBalance $record): string => $record->party_type === 'CUSTOMER' ? 'View Customer' : 'View Vendor')
                ->icon('heroicon-o-user')
                ->visible(fn (SubledgerOpeningBalance $record): bool => $record->party_type === 'CUSTOMER'
                    ? $record->customer_id !== null && auth()->user()?->can('view', $record->customer) === true
                    : $record->vendor_id !== null && auth()->user()?->can('view', $record->vendor) === true)
                ->url(fn (SubledgerOpeningBalance $record): string => $record->party_type === 'CUSTOMER'
                    ? CustomerResource::getUrl('view', ['record' => $record->customer_id])
                    : VendorResource::getUrl('view', ['record' => $record->vendor_id])),
            EditAction::make()
                ->visible(fn (SubledgerOpeningBalance $record): bool => $record->status === SubledgerOpeningBalance::STATUS_DRAFT
                    && auth()->user()?->can('update', $record) === true),
            // Action::configureUsing() applies the shared password confirmation
            // to sensitive action names, so do not wrap this action twice.
            Action::make('post')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (SubledgerOpeningBalance $record): bool => $record->status === SubledgerOpeningBalance::STATUS_DRAFT
                    && auth()->user()?->can('post', $record) === true)
                ->action(function (SubledgerOpeningBalance $record): void {
                    try {
                        $this->record = app(SubledgerOpeningBalanceService::class)->post($record, auth()->id());
                        $this->refreshInfolistRecord();
                        Notification::make()->title('Opening balance posted')->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title('Opening balance was not posted')->body($exception->getMessage())->danger()->send();
                    }
                }),
            SensitiveActionPasswordConfirmation::protect(
                Action::make('reverse')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (SubledgerOpeningBalance $record): bool => $record->status === SubledgerOpeningBalance::STATUS_POSTED
                        && auth()->user()?->can('reverse', $record) === true)
                    ->requiresConfirmation()
                    ->action(function (SubledgerOpeningBalance $record, array $data): void {
                        try {
                            $this->record = app(SubledgerOpeningBalanceService::class)->reverse($record, (string) ($data['reason'] ?? 'Controlled reversal'), auth()->id());
                            $this->refreshInfolistRecord();
                            Notification::make()->title('Opening balance reversed')->success()->send();
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('Opening balance was not reversed')->body($exception->getMessage())->danger()->send();
                        }
                    })
                    ->schema([Textarea::make('reason')->required()->default('Controlled reversal')]),
            ),
        ];
    }

    private function refreshInfolistRecord(): void
    {
        $this->record = $this->getRecord()->fresh();
        $this->getSchema('infolist')->record($this->getRecord());
    }
}
