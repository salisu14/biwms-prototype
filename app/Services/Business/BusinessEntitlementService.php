<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\Business;
use App\Models\User;
use App\Models\UserBusiness;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class BusinessEntitlementService
{
    public function __construct(
        private readonly AuditTrailService $auditTrail
    ) {}

    public function grant(User $user, Business $business, ?int $actorId = null): UserBusiness
    {
        Gate::forUser(auth()->user())->authorize('update', $user);

        return DB::transaction(function () use ($user, $business, $actorId): UserBusiness {
            $assignment = UserBusiness::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'business_id' => $business->getKey()],
                ['granted_by' => $actorId ?? auth()->id()]
            );

            if ($assignment->wasRecentlyCreated) {
                $this->auditTrail->recordGeneric(
                    eventType: 'security',
                    action: 'business_access_granted',
                    auditable: $user,
                    userId: $actorId,
                    description: 'Business access granted to user.',
                    newValues: ['business_id' => $business->getKey()],
                    metadata: ['business_id' => $business->getKey()],
                );
            }

            return $assignment;
        });
    }

    public function revoke(User $user, Business $business, ?int $actorId = null): void
    {
        Gate::forUser(auth()->user())->authorize('update', $user);

        if (auth()->id() === $user->getKey()) {
            $remaining = UserBusiness::query()
                ->where('user_id', $user->getKey())
                ->where('business_id', '!=', $business->getKey())
                ->exists();

            if (! $remaining && ! $user->hasRole('super_admin')) {
                throw ValidationException::withMessages([
                    'business_id' => 'You cannot remove the last business access from your own account.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $business, $actorId): void {
            $deleted = UserBusiness::query()
                ->where('user_id', $user->getKey())
                ->where('business_id', $business->getKey())
                ->delete();

            if ($deleted) {
                $this->auditTrail->recordGeneric(
                    eventType: 'security',
                    action: 'business_access_revoked',
                    auditable: $user,
                    userId: $actorId,
                    description: 'Business access revoked from user.',
                    oldValues: ['business_id' => $business->getKey()],
                    metadata: ['business_id' => $business->getKey()],
                );
            }
        });
    }
}
