<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Pages;

use App\Filament\Resources\AttendanceReviewPeriods\AttendanceReviewPeriodResource;
use App\Services\Hr\AttendanceReviewPeriodService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAttendanceReviewPeriod extends CreateRecord
{
    protected static string $resource = AttendanceReviewPeriodResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(AttendanceReviewPeriodService::class)->create($data, auth()->user());
    }
}
