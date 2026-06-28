<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditTrailService;

class UserAuditObserver
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'user_created',
            auditable: $user,
            description: "User {$user->email} created",
            newValues: $this->safeAttributes($user->getAttributes()),
        );
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('password')) {
            $this->auditTrailService->recordGeneric(
                eventType: 'security',
                action: 'password_changed',
                auditable: $user,
                description: "Password changed for user {$user->email}",
            );
        }

        $changedAttributes = collect($user->getChanges())
            ->except(['updated_at', 'password', 'remember_token'])
            ->all();

        if ($changedAttributes === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changedAttributes) as $key) {
            $oldValues[$key] = $user->getOriginal($key);
        }

        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'user_updated',
            auditable: $user,
            description: "User {$user->email} updated",
            oldValues: $this->safeAttributes($oldValues),
            newValues: $this->safeAttributes($changedAttributes),
        );
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'user_deleted',
            auditable: $user,
            description: "User {$user->email} deleted",
            oldValues: $this->safeAttributes($user->getOriginal()),
        );
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'user_restored',
            auditable: $user,
            description: "User {$user->email} restored",
        );
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'user_force_deleted',
            auditable: $user,
            description: "User {$user->email} force deleted",
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function safeAttributes(array $attributes): array
    {
        return collect($attributes)
            ->except([
                'password',
                'remember_token',
                'two_factor_secret',
                'two_factor_recovery_codes',
            ])
            ->all();
    }
}
