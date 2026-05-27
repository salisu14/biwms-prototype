<?php

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class SalesInvoicePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.invoice.view_any',
            'view:any:sales_invoice',
            'sales_invoice_access',
        ]);
    }

    public function view(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.view',
            'view:sales_invoice',
            'sales_invoice_show',
            'sales_invoice_access',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'sales.invoice.create',
            'create:sales_invoice',
            'sales_invoice_create',
        ]);
    }

    public function update(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.update',
            'edit:sales_invoice',
            'sales_invoice_edit',
        ]);
    }

    public function delete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.delete',
            'delete:sales_invoice',
            'sales_invoice_delete',
        ]);
    }

    public function post(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.post',
            'post:sales_invoice',
            'post:invoice',
        ]);
    }

    public function restore(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->delete($user, $salesInvoice);
    }

    public function forceDelete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->delete($user, $salesInvoice);
    }
}
