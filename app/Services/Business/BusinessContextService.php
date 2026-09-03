<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class BusinessContextService
{
    public function resolve(?int $requestedBusinessId = null, ?Business $ownedBusiness = null, bool $requireActive = true): ?Business
    {
        $candidateId = $ownedBusiness?->getKey()
            ?? ($requestedBusinessId !== null && $requestedBusinessId > 0 ? $requestedBusinessId : $this->sessionBusinessId());

        if ($candidateId === null) {
            return null;
        }

        $business = $ownedBusiness?->exists
            ? $ownedBusiness
            : Business::query()->find($candidateId);

        if (! $business || ($requireActive && ! $business->is_active) || ! $this->canAccess($business)) {
            throw new AuthorizationException('You are not authorized to access the selected business.');
        }

        return $business;
    }

    public function resolveId(?int $requestedBusinessId = null, ?Business $ownedBusiness = null, bool $requireActive = true): ?int
    {
        return $this->resolve($requestedBusinessId, $ownedBusiness, $requireActive)?->getKey();
    }

    public function setActive(?int $businessId): Business
    {
        $business = $this->resolve($businessId);
        if (! $business) {
            throw new AuthorizationException('A valid business must be selected.');
        }

        session(['active_business_id' => $business->getKey()]);

        return $business;
    }

    public function canAccess(Business $business, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return app()->runningInConsole();
        }

        if ($user->hasRole('super_admin')) {
            return (bool) $business->is_active;
        }

        if (! $business->relationLoaded('users')) {
            if ($business->users()->whereKey($user->getKey())->exists()) {
                return true;
            }

            // Legacy installations had one implicit company context. Preserve
            // that contract only while exactly one active business exists and
            // the user has no explicit assignments; never use this in a
            // multi-business environment.
            return Business::query()->where('is_active', true)->count() === 1
                && ! $user->businesses()->exists();
        }

        return $business->users->contains($user);
    }

    /** @return array<int, Business> */
    public function authorizedBusinesses(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user) {
            return [];
        }

        $query = Business::query()->where('is_active', true)->orderBy('name');
        if (! $user->hasRole('super_admin')) {
            $query->whereHas('users', fn ($builder) => $builder->whereKey($user->getKey()));
        }

        return $query->get()->all();
    }

    private function sessionBusinessId(): ?int
    {
        $value = session('active_business_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
