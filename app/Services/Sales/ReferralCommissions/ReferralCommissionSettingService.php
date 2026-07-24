<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\ReferralCommissionPlanStatus;
use App\Models\Business;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionSetting;
use App\Services\AuditTrailService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReferralCommissionSettingService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveForBusiness(Business $business, array $data, ?int $actorId = null): ReferralCommissionSetting
    {
        return DB::transaction(function () use ($business, $data, $actorId): ReferralCommissionSetting {
            $business = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $setting = ReferralCommissionSetting::query()
                ->where('business_id', $business->getKey())
                ->lockForUpdate()
                ->first();

            $this->validate($business, $data);

            $payload = [
                ...$data,
                'business_id' => $business->getKey(),
                'updated_by' => $actorId,
            ];

            if ($setting) {
                $oldValues = $setting->getAttributes();
                $setting->update($payload);
                $action = $setting->wasChanged('is_enabled')
                    ? ($setting->is_enabled ? 'enabled' : 'disabled')
                    : 'updated';

                $this->auditTrailService->recordGeneric(
                    eventType: 'referral_commission_setting_changed',
                    action: "settings_{$action}",
                    auditable: $setting,
                    documentType: 'REFERRAL_COMMISSION_SETTING',
                    userId: $actorId,
                    oldValues: $oldValues,
                    newValues: $setting->getAttributes(),
                    metadata: ['business_id' => $business->getKey()],
                );

                return $setting;
            }

            $setting = ReferralCommissionSetting::query()->create([
                ...$payload,
                'created_by' => $actorId,
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'referral_commission_setting_created',
                action: 'settings_created',
                auditable: $setting,
                documentType: 'REFERRAL_COMMISSION_SETTING',
                userId: $actorId,
                newValues: $setting->getAttributes(),
                metadata: ['business_id' => $business->getKey()],
            );

            return $setting;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(Business $business, array $data): void
    {
        $decimalPlaces = (int) ($data['commission_decimal_places'] ?? 4);
        if ($decimalPlaces < 0 || $decimalPlaces > 6) {
            throw new DomainException('Commission decimal places must be between 0 and 6.');
        }

        $minimumAmount = $data['minimum_eligible_sale_amount'] ?? null;
        if ($minimumAmount !== null && (float) $minimumAmount < 0) {
            throw new DomainException('Minimum eligible sale amount cannot be negative.');
        }

        $defaultPlanId = $data['default_plan_id'] ?? null;
        if ($defaultPlanId !== null) {
            $defaultPlan = ReferralCommissionPlan::query()->findOrFail($defaultPlanId);

            if ($defaultPlan->business_id !== null && (int) $defaultPlan->business_id !== (int) $business->getKey()) {
                throw new DomainException('Default plan must belong to the same business.');
            }

            if ($defaultPlan->status !== ReferralCommissionPlanStatus::ACTIVE) {
                throw new DomainException('Default plan must be active.');
            }
        }

        if (($data['require_plan_assignment'] ?? true) === false && $defaultPlanId === null) {
            throw new DomainException('An active default plan is required when explicit plan assignment is not required.');
        }
    }
}
