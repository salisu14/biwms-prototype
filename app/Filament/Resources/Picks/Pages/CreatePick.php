<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Pages;

use App\Filament\Resources\Picks\PickResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePick extends CreateRecord
{
    protected static string $resource = PickResource::class;
}
