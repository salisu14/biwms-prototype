<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

#[Signature('biwms:subledger-opening-permissions-sync {--dry-run : Report assignments without changing roles} {--apply : Add the approved permissions to roles}')]
#[Description('Add Subledger Opening Balance permissions to approved roles without removing existing assignments.')]
final class BiwmsSubledgerOpeningPermissionsSync extends Command
{
    /** @var array<int, string> */
    private array $permissions = [
        'finance.subledger_opening_balance.view_any',
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.update',
        'finance.subledger_opening_balance.delete',
        'finance.subledger_opening_balance.delete_any',
        'finance.subledger_opening_balance.restore',
        'finance.subledger_opening_balance.restore_any',
        'finance.subledger_opening_balance.force_delete',
        'finance.subledger_opening_balance.force_delete_any',
        'finance.subledger_opening_balance.post',
        'finance.subledger_opening_balance.reverse',
    ];

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::INVALID;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = Permission::query()->whereIn('name', $this->permissions)->where('guard_name', 'web')->get();
        $missingPermissions = array_diff($this->permissions, $permissions->pluck('name')->all());
        if ($missingPermissions !== []) {
            $this->error('Required permissions are missing: '.implode(', ', $missingPermissions));

            return self::FAILURE;
        }

        $roleActions = [
            'finance-accountant' => ['view_any', 'view', 'create', 'update', 'post', 'reverse'],
            'finance-manager' => ['view_any', 'view', 'create', 'update', 'delete', 'post', 'reverse'],
            'admin' => $this->permissions,
            'super_admin' => $this->permissions,
        ];
        $roles = Role::query()->whereIn('name', array_keys($roleActions))->where('guard_name', 'web')->get()->keyBy('name');
        $missingRoles = array_diff(array_keys($roleActions), $roles->keys()->all());
        if ($missingRoles !== []) {
            $this->error('Required roles are missing: '.implode(', ', $missingRoles));

            return self::FAILURE;
        }

        foreach ($roleActions as $roleName => $actions) {
            $role = $roles->get($roleName);
            $names = $roleName === 'admin' || $roleName === 'super_admin'
                ? $this->permissions
                : array_map(fn (string $action): string => "finance.subledger_opening_balance.{$action}", $actions);
            $missing = $permissions->whereIn('name', $names)->reject(fn (Permission $permission): bool => $role->hasPermissionTo($permission))->pluck('name')->all();
            $this->line("{$roleName}: ".($missing === [] ? 'already up to date' : 'would add '.implode(', ', $missing)));

            if ($this->option('apply') && $missing !== []) {
                DB::transaction(fn (): mixed => $role->givePermissionTo($permissions->whereIn('name', $missing)->all()));
            }
        }

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to add permissions.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
