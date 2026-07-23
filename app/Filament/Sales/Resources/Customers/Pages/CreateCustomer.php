<?php

namespace App\Filament\Sales\Resources\Customers\Pages;

use App\Filament\Sales\Resources\Customers\CustomerResource;
use App\Models\Referrer;
use App\Services\Sales\CustomerReferralService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $referral = $this->extractReferralData($data);
        $customer = parent::handleRecordCreation($data);

        if ($referral['referrer_id'] !== null) {
            app(CustomerReferralService::class)->assign(
                $customer,
                Referrer::query()->findOrFail($referral['referrer_id']),
                [
                    'effective_from' => $referral['effective_from'],
                    'referred_at' => $referral['effective_from'],
                    'referral_source' => $referral['source'],
                    'notes' => $referral['notes'],
                ],
                auth()->id(),
            );
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{referrer_id: mixed, effective_from: mixed, source: mixed, notes: mixed}
     */
    private function extractReferralData(array &$data): array
    {
        $referral = [
            'referrer_id' => $data['referral_referrer_id'] ?? null,
            'effective_from' => $data['referral_effective_from'] ?? today(),
            'source' => $data['referral_source'] ?? null,
            'notes' => $data['referral_notes'] ?? null,
        ];

        unset($data['referral_referrer_id'], $data['referral_effective_from'], $data['referral_source'], $data['referral_notes']);

        return $referral;
    }
}
