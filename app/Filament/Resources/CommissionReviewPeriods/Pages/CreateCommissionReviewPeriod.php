<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionReviewPeriods\Pages;

use App\Filament\Resources\CommissionReviewPeriods\CommissionReviewPeriodResource;
use App\Services\Sales\ReferralCommissions\CommissionReviewPeriodService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommissionReviewPeriod extends CreateRecord
{
    protected static string $resource = CommissionReviewPeriodResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CommissionReviewPeriodService::class)->create($data, auth()->user());
    }
}
