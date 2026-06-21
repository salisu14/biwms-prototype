<?php

namespace App\Filament\Resources\PettyCashTransactions\Pages;

use App\Filament\Resources\PettyCashTransactions\PettyCashTransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPettyCashTransaction extends ViewRecord
{
    protected static string $resource = PettyCashTransactionResource::class;
}
