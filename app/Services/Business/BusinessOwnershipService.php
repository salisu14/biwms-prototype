<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Exceptions\BusinessException;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class BusinessOwnershipService
{
    public function requireId(?int $businessId, string $subject): int
    {
        if ($businessId === null || $businessId < 1) {
            throw new BusinessException(
                "Unable to determine the business that owns this {$subject}.",
                title: 'Business ownership is required',
                field: 'business_id',
            );
        }

        return $businessId;
    }

    /**
     * Validate AUTHORITATIVE PERSISTED/PARENT ownership.
     *
     * Used when ownership is taken from an already-owned, trusted domain record
     * (a persisted parent document, or a persisted document/payment at posting
     * time). Proves the business exists and is active. It never substitutes
     * active session ownership.
     */
    public function requirePersistedId(?int $businessId, string $subject): int
    {
        if ($businessId === null || $businessId < 1) {
            throw new BusinessException(
                "Unable to determine the business that owns this {$subject}.",
                title: 'Business ownership is required',
                field: 'business_id',
            );
        }

        $business = Business::query()->find($businessId);

        if (! $business) {
            throw new BusinessException(
                "The business that owns this {$subject} does not exist.",
                title: 'Business ownership is required',
                field: 'business_id',
            );
        }

        if (! $business->is_active) {
            throw new BusinessException(
                "The business that owns this {$subject} is not active.",
                title: 'Business ownership is required',
                field: 'business_id',
            );
        }

        return (int) $business->getKey();
    }

    /**
     * Resolve ownership from USER/ACTIVE CONTEXT for a new interactive document.
     *
     * An explicitly supplied id is authorized through the existing
     * BusinessContextService entitlement semantics (existence, active status and
     * user access); otherwise the active session business is used. A supplied
     * positive integer does not by itself bypass access authorization.
     */
    public function requireActiveContextId(?int $businessId, string $subject): int
    {
        $context = app(BusinessContextService::class);

        try {
            $resolvedId = $businessId ?? $context->resolveId();

            if ($resolvedId === null || $resolvedId < 1) {
                throw new BusinessException(
                    "Unable to determine the business that owns this {$subject}.",
                    title: 'Business ownership is required',
                    field: 'business_id',
                );
            }

            $business = $context->resolve($resolvedId);
        } catch (AuthorizationException $exception) {
            throw new BusinessException(
                "You are not authorized to use the selected business for this {$subject}.",
                title: 'Business ownership is required',
                field: 'business_id',
                previous: $exception,
            );
        }

        if (! $business) {
            throw new BusinessException(
                "Unable to determine the business that owns this {$subject}.",
                title: 'Business ownership is required',
                field: 'business_id',
            );
        }

        return (int) $business->getKey();
    }

    public function idFrom(Model $model): ?int
    {
        if (! array_key_exists('business_id', $model->getAttributes())) {
            return null;
        }

        $businessId = $model->getAttribute('business_id');

        return $businessId === null ? null : (int) $businessId;
    }

    public function assertSame(?int $expected, ?int $actual, string $subject): void
    {
        if ($expected !== null && $actual !== null && $expected !== $actual) {
            throw new BusinessException(
                "The {$subject} belongs to a different business.",
                title: 'Business ownership mismatch',
                field: 'business_id',
            );
        }
    }
}
