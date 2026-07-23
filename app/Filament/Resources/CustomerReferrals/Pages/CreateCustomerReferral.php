<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerReferrals\Pages;

use App\Filament\Resources\CustomerReferrals\CustomerReferralResource;
use App\Models\Customer;
use App\Models\Referrer;
use App\Services\Sales\CustomerReferralService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomerReferral extends CreateRecord
{
    protected static string $resource = CustomerReferralResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $referrer = Referrer::query()->findOrFail($data['referrer_id']);

        return app(CustomerReferralService::class)->assign($customer, $referrer, $data, auth()->id());
    }
}
