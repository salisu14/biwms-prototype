<?php

namespace App\Policies;

use App\Models\AuditTrail;
use App\Models\User;

class AuditTrailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_trail.view') || $user->can('audit_trail.view_any');
    }

    public function view(User $user, AuditTrail $auditTrail): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditTrail $auditTrail): bool
    {
        return false;
    }

    public function delete(User $user, AuditTrail $auditTrail): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AuditTrail $auditTrail): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditTrail $auditTrail): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
