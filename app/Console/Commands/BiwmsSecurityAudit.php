<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Support\FilamentPermissionRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:security-audit {--json : Output machine-readable JSON}')]
#[Description('Audit BIWMS Filament authorization metadata, policies, and permission names.')]
class BiwmsSecurityAudit extends Command
{
    public function handle(FilamentPermissionRegistry $registry): int
    {
        $resources = $registry->resources();
        $generatedPermissions = $registry->generatedPermissionNames();

        $missingPermissionModule = [];
        $missingPermissionResource = [];
        $resourcesWithoutPolicies = [];

        foreach ($resources as $resourceClass) {
            if (! method_exists($resourceClass, 'permissionModule')) {
                $missingPermissionModule[] = $resourceClass;
            }

            if (! method_exists($resourceClass, 'permissionResource')) {
                $missingPermissionResource[] = $resourceClass;
            }

            if (method_exists($resourceClass, 'getModel') && Gate::getPolicyFor($resourceClass::getModel()) === null) {
                $resourcesWithoutPolicies[] = [
                    'resource' => $resourceClass,
                    'model' => $resourceClass::getModel(),
                ];
            }
        }

        $existingPermissions = Schema::hasTable('permissions')
            ? Permission::query()->pluck('name')->all()
            : [];

        $missingGeneratedPermissions = array_values(array_diff($generatedPermissions, $existingPermissions));
        $wrongPatternPermissions = array_values(array_filter(
            $existingPermissions,
            fn (string $permission): bool => (bool) preg_match('/^(view_any|view|create|update|delete|delete_any|restore|restore_any|force_delete|force_delete_any)_[a-z0-9_]+$/', $permission)
        ));

        $report = [
            'resources_without_permission_module' => $missingPermissionModule,
            'resources_without_permission_resource' => $missingPermissionResource,
            'resources_without_policies' => $resourcesWithoutPolicies,
            'missing_generated_permissions' => $missingGeneratedPermissions,
            'wrong_pattern_permissions' => $wrongPatternPermissions,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Security Audit');
        $this->line('Resources scanned: '.count($resources));
        $this->line('Generated permissions expected: '.count($generatedPermissions));
        $this->newLine();

        $this->section('Resources without permissionModule()', $missingPermissionModule);
        $this->section('Resources without permissionResource()', $missingPermissionResource);
        $this->section('Resources without policies', array_map(
            fn (array $item): string => $item['resource'].' -> '.$item['model'],
            $resourcesWithoutPolicies
        ));
        $this->section('Generated permissions missing from DB', $missingGeneratedPermissions);
        $this->section('Wrong-pattern generated permissions present', $wrongPatternPermissions);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function section(string $title, array $items): void
    {
        $this->warn($title.': '.count($items));

        foreach (array_slice($items, 0, 50) as $item) {
            $this->line(' - '.$item);
        }

        if (count($items) > 50) {
            $this->line(' ... '.(count($items) - 50).' more');
        }

        $this->newLine();
    }
}
