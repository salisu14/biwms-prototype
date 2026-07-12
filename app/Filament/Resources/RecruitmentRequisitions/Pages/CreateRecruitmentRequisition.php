<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentRequisitions\Pages;

use App\Filament\Resources\RecruitmentRequisitions\RecruitmentRequisitionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentRequisition extends CreateRecord
{
    protected static string $resource = RecruitmentRequisitionResource::class;
}
