<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubledgerOpeningBalance;
use App\Models\User;

final class SubledgerOpeningBalancePolicy extends BaseFilamentPolicy
{
    protected string $module = 'finance';

    protected string $resource = 'subledger_opening_balance';

    public function viewAny(User $user): bool
    {
        return $this->canOpening($user, 'view_any') || $this->canOpening($user, 'view');
    }

    public function view(User $user, mixed $model): bool
    {
        return $model instanceof SubledgerOpeningBalance
            && $this->canOpening($user, 'view')
            && $this->withinBusiness($model);
    }

    public function create(User $user): bool
    {
        return $this->canOpening($user, 'create');
    }

    public function createCustomer(User $user): bool
    {
        return $this->canOpening($user, 'create', 'CUSTOMER');
    }

    public function createVendor(User $user): bool
    {
        return $this->canOpening($user, 'create', 'VENDOR');
    }

    public function update(User $user, mixed $model): bool
    {
        return $model instanceof SubledgerOpeningBalance
            && $model->status === SubledgerOpeningBalance::STATUS_DRAFT
            && $this->canOpening($user, 'update', $model->party_type)
            && $this->withinBusiness($model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $model instanceof SubledgerOpeningBalance
            && $model->status === SubledgerOpeningBalance::STATUS_DRAFT
            && $this->canOpening($user, 'delete', $model->party_type)
            && $this->withinBusiness($model);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, mixed $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function post(User $user, SubledgerOpeningBalance $model): bool
    {
        return $this->canOpening($user, 'post', $model->party_type)
            && $this->withinBusiness($model);
    }

    public function reverse(User $user, SubledgerOpeningBalance $model): bool
    {
        return $model->status === SubledgerOpeningBalance::STATUS_POSTED
            && $this->canOpening($user, 'reverse', $model->party_type)
            && $this->withinBusiness($model);
    }

    private function canOpening(User $user, string $action, ?string $partyType = null): bool
    {
        $prefixes = match (strtoupper((string) $partyType)) {
            'CUSTOMER' => ['finance.customer_opening_balance', 'finance.subledger_opening_balance'],
            'VENDOR' => ['finance.vendor_opening_balance', 'finance.subledger_opening_balance'],
            default => ['finance.customer_opening_balance', 'finance.vendor_opening_balance', 'finance.subledger_opening_balance'],
        };

        return collect($prefixes)->contains(fn (string $prefix): bool => $user->can("{$prefix}.{$action}"));
    }

    private function withinBusiness(SubledgerOpeningBalance $model): bool
    {
        $active = (int) session('active_business_id', 0);

        return $active === 0 || (int) $model->business_id === $active;
    }
}
