<?php

namespace App\Filament\Resources\PettyCashVouchers\Pages;

use App\Filament\Resources\PettyCashVouchers\PettyCashVoucherResource;
use App\Models\PettyCashVoucher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPettyCashVoucher extends EditRecord
{
    protected static string $resource = PettyCashVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make()
                ->visible(fn (PettyCashVoucher $record) => $record->status->value === 'pending'),

            DeleteAction::make()
                ->visible(fn (PettyCashVoucher $record) => $record->status->value === 'pending'),
        ];
    }
}
