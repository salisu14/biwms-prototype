<?php

namespace App\Policies;

use App\Models\SalesQuote;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class SalesQuotePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.quote.view_any',
            'view:any:sales_quote',
            'sales_quote_access',
        ]);
    }

    public function view(User $user, SalesQuote $salesQuote): bool
    {
        return $this->canAny($user, [
            'sales.quote.view',
            'view:sales_quote',
            'sales_quote_show',
            'sales_quote_access',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'sales.quote.create',
            'create:sales_quote',
            'sales_quote_create',
        ]);
    }

    public function update(User $user, SalesQuote $salesQuote): bool
    {
        return $this->canAny($user, [
            'sales.quote.update',
            'edit:sales_quote',
            'sales_quote_edit',
        ]);
    }

    public function delete(User $user, SalesQuote $salesQuote): bool
    {
        return $this->canAny($user, [
            'sales.quote.delete',
            'delete:sales_quote',
            'sales_quote_delete',
        ]);
    }

    public function approve(User $user, SalesQuote $salesQuote): bool
    {
        return $this->canAny($user, [
            'sales.quote.approve',
            'approve:sales_quote',
        ]);
    }

    public function convert(User $user, SalesQuote $salesQuote): bool
    {
        return $this->canAny($user, [
            'sales.quote.convert',
            'convert:sales_quote',
            'create:sales_order',
            'create:order',
        ]);
    }

    public function restore(User $user, SalesQuote $salesQuote): bool
    {
        return $this->delete($user, $salesQuote);
    }

    public function forceDelete(User $user, SalesQuote $salesQuote): bool
    {
        return $this->delete($user, $salesQuote);
    }
}
