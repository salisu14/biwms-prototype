<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Resources\Resource;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FilamentPermissionRegistry
{
    /**
     * @var array<int, class-string<\Filament\Resources\Resource>>|null
     */
    private static ?array $resourceClasses = null;

    /**
     * @var array<class-string, array{module: string, resource: string}|null>
     */
    private static array $permissionPartsByModel = [];

    /**
     * @var array<class-string<\Filament\Resources\Resource>, array{module: string, resource: string}>
     */
    private static array $permissionPartsByResource = [];

    /**
     * @return array<int, class-string<\Filament\Resources\Resource>>
     */
    public function resources(): array
    {
        if (self::$resourceClasses !== null) {
            return self::$resourceClasses;
        }

        $resourcePath = app_path('Filament/Resources');

        if (! File::exists($resourcePath)) {
            return [];
        }

        return self::$resourceClasses = collect(File::allFiles($resourcePath))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), 'Resource.php'))
            ->map(function ($file): string {
                return 'App\\Filament\\Resources\\'
                    .str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            })
            ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Resource::class))
            ->values()
            ->all();
    }

    /**
     * @return array{module: string, resource: string}
     */
    public function permissionPartsForResource(string $resourceClass): array
    {
        if (isset(self::$permissionPartsByResource[$resourceClass])) {
            return self::$permissionPartsByResource[$resourceClass];
        }

        return self::$permissionPartsByResource[$resourceClass] = [
            'module' => method_exists($resourceClass, 'permissionModule')
                ? $resourceClass::permissionModule()
                : $this->inferModule($resourceClass),
            'resource' => method_exists($resourceClass, 'permissionResource')
                ? $resourceClass::permissionResource()
                : $this->inferResource($resourceClass),
        ];
    }

    /**
     * @return array{module: string, resource: string}|null
     */
    public function permissionPartsForModel(string $modelClass): ?array
    {
        if (array_key_exists($modelClass, self::$permissionPartsByModel)) {
            return self::$permissionPartsByModel[$modelClass];
        }

        foreach ($this->resources() as $resourceClass) {
            if (! method_exists($resourceClass, 'getModel') || $resourceClass::getModel() !== $modelClass) {
                continue;
            }

            return self::$permissionPartsByModel[$modelClass] = $this->permissionPartsForResource($resourceClass);
        }

        return self::$permissionPartsByModel[$modelClass] = null;
    }

    /**
     * @return array<int, string>
     */
    public function generatedPermissionNames(): array
    {
        $actions = [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'restore',
            'restore_any',
            'force_delete',
            'force_delete_any',
        ];

        $permissions = [];

        foreach ($this->resources() as $resourceClass) {
            $parts = $this->permissionPartsForResource($resourceClass);

            foreach ($actions as $action) {
                $permissions[] = "{$parts['module']}.{$parts['resource']}.{$action}";
            }
        }

        return array_values(array_unique($permissions));
    }

    public function inferModule(string $resourceClass): string
    {
        $namespace = Str::of($resourceClass)->after('App\\Filament\\Resources\\')->before('\\')->snake()->toString();

        return match (true) {
            str_contains($resourceClass, 'Price')
                || str_contains($resourceClass, 'Discount')
                || str_contains($resourceClass, 'Campaign') => 'pricing',
            str_contains($resourceClass, 'Purchase')
                || str_contains($resourceClass, 'Vendor')
                || str_contains($resourceClass, 'BlanketPurchase') => 'procurement',
            str_contains($resourceClass, 'Sales')
                || str_contains($resourceClass, 'Customer') => 'sales',
            str_contains($resourceClass, 'Warehouse')
                || str_contains($resourceClass, 'Bin')
                || str_contains($resourceClass, 'Zone')
                || str_contains($resourceClass, 'Inventory')
                || str_contains($resourceClass, 'ItemLedger') => 'warehouse',
            str_contains($resourceClass, 'Production')
                || str_contains($resourceClass, 'Routing')
                || str_contains($resourceClass, 'WorkCenter')
                || str_contains($resourceClass, 'MachineCenter')
                || str_contains($resourceClass, 'Overhead') => 'factory',
            str_contains($resourceClass, 'Employee')
                || str_contains($resourceClass, 'Payroll')
                || str_contains($resourceClass, 'PayCode')
                || str_contains($resourceClass, 'Attendance') => 'hr',
            str_contains($resourceClass, 'FixedAsset')
                || str_contains($resourceClass, 'Depreciation')
                || str_contains($resourceClass, 'FA') => 'fixed_asset',
            str_contains($resourceClass, 'Payment')
                || str_contains($resourceClass, 'Bank')
                || str_contains($resourceClass, 'Journal')
                || str_contains($resourceClass, 'Currency')
                || str_contains($resourceClass, 'Account')
                || str_contains($resourceClass, 'Posting')
                || str_contains($resourceClass, 'Vat')
                || str_contains($resourceClass, 'Expense')
                || str_contains($resourceClass, 'ValueEntry') => 'finance',
            str_contains($resourceClass, 'User')
                || str_contains($resourceClass, 'Role')
                || str_contains($resourceClass, 'AuditTrail') => 'admin',
            default => $namespace !== '' ? $namespace : 'admin',
        };
    }

    public function inferResource(string $resourceClass): string
    {
        $modelClass = method_exists($resourceClass, 'getModel') ? $resourceClass::getModel() : null;

        if (is_string($modelClass) && class_exists($modelClass)) {
            return Str::snake(class_basename($modelClass));
        }

        return Str::of(class_basename($resourceClass))
            ->beforeLast('Resource')
            ->snake()
            ->toString();
    }
}
