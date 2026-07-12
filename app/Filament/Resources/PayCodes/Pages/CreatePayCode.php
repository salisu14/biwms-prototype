<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayCodes\Pages;

use App\Filament\Resources\PayCodes\PayCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayCode extends CreateRecord
{
    protected static string $resource = PayCodeResource::class;
}
