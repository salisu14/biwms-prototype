<?php

namespace App\Filament\Resources\SubledgerOpeningBalances\Pages;

use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Services\Finance\SubledgerOpeningBalanceService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSubledgerOpeningBalance extends EditRecord
{
    protected static string $resource = SubledgerOpeningBalanceResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SubledgerOpeningBalanceService::class)->updateDraft($record, $data);
    }
}
