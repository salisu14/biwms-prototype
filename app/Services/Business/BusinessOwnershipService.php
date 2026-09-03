<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Exceptions\BusinessException;
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
