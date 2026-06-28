<?php

namespace App\Policies;

use App\Models\PostedSalesInvoice;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PostedSalesInvoicePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.posted_sales_invoice.view_any',
            'sales.posted_sales_invoice.view',
        ]);
    }

    public function view(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.posted_sales_invoice.view',
            'sales.posted_sales_invoice.view_any',
        ]);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return false;
    }

    public function delete(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function print(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.posted_sales_invoice.print',
            'sales.posted_sales_invoice.view',
        ]);
    }

    public function export(User $user, PostedSalesInvoice $postedSalesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.posted_sales_invoice.export',
            'sales.posted_sales_invoice.view',
        ]);
    }
}
