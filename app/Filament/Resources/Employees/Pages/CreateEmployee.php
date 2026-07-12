<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Services\HR\EmployeeOnboardingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Intercept creation to use our Onboarding Service.
     * This wraps Employee + User creation in a single DB Transaction.
     *
     * @throws Throwable
     */
    public function handleRecordCreation(array $data): Model
    {
        return app(EmployeeOnboardingService::class)->create($data);
    }
}
