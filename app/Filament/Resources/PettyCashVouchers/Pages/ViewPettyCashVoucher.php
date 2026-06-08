<?php

namespace App\Filament\Resources\PettyCashVouchers\Pages;

use App\Enums\PettyCashVoucherStatus;
use App\Filament\Resources\PettyCashVouchers\PettyCashVoucherResource;
use App\Models\PettyCashVoucher;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPettyCashVoucher extends ViewRecord
{
    protected static string $resource = PettyCashVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('print')
                ->label('Print Voucher')
                ->icon('heroicon-m-printer')
                ->url(fn (PettyCashVoucher $record) => route('petty-cash.vouchers.print', $record))
                ->openUrlInNewTab()
                ->visible(fn ($record) => in_array($record->status, [
                    PettyCashVoucherStatus::APPROVED,
                    PettyCashVoucherStatus::POSTED
                ])),
        ];
    }
}
