<?php

namespace App\Filament\Resources\JournalLines\Pages;

use App\Filament\Resources\JournalLines\JournalLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalLine extends CreateRecord
{
    protected static string $resource = JournalLineResource::class;
}
