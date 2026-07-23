<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\CustomerReferralStatus;
use App\Enums\ReferrerType;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\Referrer;
use App\Services\AuditTrailService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerReferralService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(Customer $customer, Referrer $referrer, array $data = [], ?int $actorId = null): CustomerReferral
    {
        return DB::transaction(function () use ($customer, $referrer, $data, $actorId): CustomerReferral {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $referrer = Referrer::query()->lockForUpdate()->findOrFail($referrer->getKey());

            $this->assertAssignable($customer, $referrer);

            if (($data['is_primary'] ?? true) === true) {
                $this->assertNoOpenActivePrimary($customer);
            }

            $referral = CustomerReferral::query()->create([
                'business_id' => $data['business_id'] ?? $referrer->business_id,
                'customer_id' => $customer->getKey(),
                'referrer_id' => $referrer->getKey(),
                'status' => $data['status'] ?? CustomerReferralStatus::ACTIVE,
                'is_primary' => $data['is_primary'] ?? true,
                'referred_at' => $data['referred_at'] ?? $data['effective_from'] ?? today(),
                'effective_from' => $data['effective_from'] ?? today(),
                'effective_to' => $data['effective_to'] ?? null,
                'referral_source' => $data['referral_source'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_assigned', 'assigned', $referral, $actorId, [
                'customer_id' => $customer->getKey(),
                'new_referrer_id' => $referrer->getKey(),
                'effective_from' => (string) $referral->effective_from?->toDateString(),
            ]);

            return $referral;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function change(Customer $customer, Referrer $newReferrer, array $data, ?int $actorId = null): CustomerReferral
    {
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw new DomainException('A reason is required when changing a referrer.');
        }

        return DB::transaction(function () use ($customer, $newReferrer, $data, $actorId, $reason): CustomerReferral {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->getKey());
            $newReferrer = Referrer::query()->lockForUpdate()->findOrFail($newReferrer->getKey());
            $current = $this->openActivePrimaryQuery($customer)->lockForUpdate()->first();
            $effectiveFrom = $data['effective_from'] ?? today();

            $this->assertAssignable($customer, $newReferrer);

            if ($current) {
                $current->update([
                    'status' => CustomerReferralStatus::ENDED,
                    'effective_to' => $this->dateString($data['effective_to'] ?? $effectiveFrom),
                    'ended_by' => $actorId,
                    'ended_at' => now(),
                    'end_reason' => $reason,
                    'updated_by' => $actorId,
                ]);
            }

            $newReferral = $this->assign($customer, $newReferrer, [
                ...$data,
                'is_primary' => true,
                'effective_from' => $effectiveFrom,
            ], $actorId);

            $this->audit('referrer_changed', 'changed', $newReferral, $actorId, [
                'customer_id' => $customer->getKey(),
                'old_referrer_id' => $current?->referrer_id,
                'new_referrer_id' => $newReferrer->getKey(),
                'effective_from' => (string) $newReferral->effective_from?->toDateString(),
                'reason' => $reason,
            ]);

            return $newReferral;
        });
    }

    public function suspend(CustomerReferral $referral, string $reason, ?int $actorId = null): CustomerReferral
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A suspension reason is required.');
        }

        return DB::transaction(function () use ($referral, $reason, $actorId): CustomerReferral {
            $referral = CustomerReferral::query()->lockForUpdate()->findOrFail($referral->getKey());
            $referral->update([
                'status' => CustomerReferralStatus::SUSPENDED,
                'suspended_by' => $actorId,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_suspended', 'suspended', $referral, $actorId, ['reason' => $reason]);

            return $referral;
        });
    }

    public function approve(CustomerReferral $referral, ?int $actorId = null): CustomerReferral
    {
        return DB::transaction(function () use ($referral, $actorId): CustomerReferral {
            $referral = CustomerReferral::query()->lockForUpdate()->findOrFail($referral->getKey());
            $customer = Customer::query()->lockForUpdate()->findOrFail($referral->customer_id);

            if ($referral->is_primary && $referral->status !== CustomerReferralStatus::ACTIVE) {
                $this->assertNoOpenActivePrimary($customer, $referral);
            }

            $referral->update([
                'status' => CustomerReferralStatus::ACTIVE,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_approved', 'approved', $referral, $actorId);

            return $referral;
        });
    }

    public function reactivate(CustomerReferral $referral, ?CarbonInterface $effectiveFrom = null, ?int $actorId = null): CustomerReferral
    {
        return DB::transaction(function () use ($referral, $effectiveFrom, $actorId): CustomerReferral {
            $referral = CustomerReferral::query()->lockForUpdate()->findOrFail($referral->getKey());
            $customer = Customer::query()->lockForUpdate()->findOrFail($referral->customer_id);

            $this->assertNoOpenActivePrimary($customer, $referral);

            $referral->update([
                'status' => CustomerReferralStatus::ACTIVE,
                'effective_from' => $effectiveFrom ?? $referral->effective_from ?? today(),
                'effective_to' => null,
                'suspended_by' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_reactivated', 'reactivated', $referral, $actorId);

            return $referral;
        });
    }

    public function end(CustomerReferral $referral, string $reason, ?CarbonInterface $effectiveTo = null, ?int $actorId = null): CustomerReferral
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('An end reason is required.');
        }

        return DB::transaction(function () use ($referral, $reason, $effectiveTo, $actorId): CustomerReferral {
            $referral = CustomerReferral::query()->lockForUpdate()->findOrFail($referral->getKey());
            $referral->update([
                'status' => CustomerReferralStatus::ENDED,
                'effective_to' => $effectiveTo ?? today(),
                'ended_by' => $actorId,
                'ended_at' => now(),
                'end_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_ended', 'ended', $referral, $actorId, ['reason' => $reason]);

            return $referral;
        });
    }

    public function cancel(CustomerReferral $referral, string $reason, ?int $actorId = null): CustomerReferral
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($referral, $reason, $actorId): CustomerReferral {
            $referral = CustomerReferral::query()->lockForUpdate()->findOrFail($referral->getKey());
            $referral->update([
                'status' => CustomerReferralStatus::CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $this->audit('referral_cancelled', 'cancelled', $referral, $actorId, ['reason' => $reason]);

            return $referral;
        });
    }

    private function assertAssignable(Customer $customer, Referrer $referrer): void
    {
        if (! $referrer->is_active) {
            throw new DomainException('Inactive referrers cannot be assigned.');
        }

        if ($referrer->type === ReferrerType::EXISTING_CUSTOMER && (int) $referrer->customer_id === (int) $customer->getKey()) {
            throw new DomainException('A customer cannot refer itself.');
        }

        $customerBusinessId = data_get($customer, 'business_id');
        if ($customerBusinessId !== null && $referrer->business_id !== null && (int) $customerBusinessId !== (int) $referrer->business_id) {
            throw new DomainException('Customer and Referrer must belong to the same business.');
        }
    }

    private function assertNoOpenActivePrimary(Customer $customer, ?CustomerReferral $except = null): void
    {
        $query = $this->openActivePrimaryQuery($customer);

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            throw new DomainException('Customer already has an active primary referrer.');
        }
    }

    private function openActivePrimaryQuery(Customer $customer)
    {
        return CustomerReferral::query()
            ->where('customer_id', $customer->getKey())
            ->where('is_primary', true)
            ->where('status', CustomerReferralStatus::ACTIVE)
            ->whereNull('effective_to');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $eventType, string $action, CustomerReferral $referral, ?int $actorId = null, array $metadata = []): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: $eventType,
            action: $action,
            auditable: $referral,
            documentType: 'CUSTOMER_REFERRAL',
            documentNo: $referral->reference,
            userId: $actorId,
            description: "Customer referral {$action}",
            metadata: [
                'customer_id' => $referral->customer_id,
                'referrer_id' => $referral->referrer_id,
                'business_id' => $referral->business_id,
                ...$metadata,
            ],
        );
    }

    private function dateString(mixed $date): mixed
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : $date;
    }
}
