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
        if ($salesInvoice->isPosted()) {
            return false;
        }

        if (! $this->canAny($user, [
            'sales.invoice.update',
            'edit:sales_invoice',
            'sales_invoice_edit',
        ])) {
            return false;
        }

        return $salesInvoice->status?->canEdit()
            || $this->approve($user, $salesInvoice)
            || $this->reopen($user, $salesInvoice);
    }

    public function delete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.delete',
            'delete:sales_invoice',
            'sales_invoice_delete',
        ]);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.invoice.delete_any',
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

    public function submit(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.submit',
            'submit:sales_invoice',
            'sales_invoice_submit',
        ]);
    }

    public function approve(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.approve',
            'approve:sales_invoice',
            'sales_invoice_approve',
        ]);
    }

    public function reject(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.reject',
            'reject:sales_invoice',
            'sales_invoice_reject',
        ]);
    }

    public function reopen(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.reopen',
            'reopen:sales_invoice',
            'sales_invoice_reopen',
        ]);
    }

    public function reverse(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.reverse',
            'reverse:sales_invoice',
            'sales_invoice_reverse',
        ]);
    }

    public function cancel(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->canAny($user, [
            'sales.invoice.cancel',
            'cancel:sales_invoice',
            'sales_invoice_cancel',
        ]);
    }

    public function restore(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->delete($user, $salesInvoice);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->delete($user, $salesInvoice);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
