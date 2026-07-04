<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use App\Support\RoleEditProfiler;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class RoleResource extends Resource
{
    /**
     * @var Collection<int, array{id: int, name: string, group: string, group_key: string, label: string, description: string}>|null
     */
    protected static ?Collection $permissionCatalog = null;

    public static function permissionModule(): string
    {
        return 'admin';
    }

    public static function permissionResource(): string
    {
        return 'role';
    }

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoleEditProfiler::measure('role_resource_form_schema_construction', fn (): Schema => $schema->components([
            Section::make('Role Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpan(1),

                    TextInput::make('guard_name')
                        ->default('web')
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make('Permissions')
                ->description('Select a module to load and edit only that module’s permissions.')
                ->schema([
                    Hidden::make('selected_permission_ids')
                        ->default([])
                        ->dehydrated(),

                    Grid::make(2)
                        ->schema([
                            Select::make('active_permission_module')
                                ->label('Permission module')
                                ->options(static::permissionModuleOptions())
                                ->default(static::defaultPermissionModuleKey())
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    $set(
                                        'active_module_permission_ids',
                                        static::activeModuleSelectedPermissionIds(
                                            (array) $get('selected_permission_ids'),
                                            $state ?: static::defaultPermissionModuleKey()
                                        )
                                    );
                                })
                                ->helperText('Only the selected module is rendered to keep the edit payload small.'),

                            Placeholder::make('module_permission_counts')
                                ->label('Selected permissions')
                                ->content(fn (Get $get): HtmlString => static::modulePermissionCountsHtml((array) $get('selected_permission_ids'))),
                        ]),

                    CheckboxList::make('active_module_permission_ids')
                        ->label(fn (Get $get): string => static::permissionModuleLabel((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())))
                        ->options(fn (Get $get): array => static::activeModulePermissionOptions((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())))
                        ->descriptions(fn (Get $get): array => static::activeModulePermissionDescriptions((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())))
                        ->columns(3)
                        ->bulkToggleable()
                        ->allowHtml()
                        ->searchable()
                        ->live()
                        ->afterStateHydrated(function (Set $set, Get $get): void {
                            $set(
                                'active_module_permission_ids',
                                static::activeModuleSelectedPermissionIds(
                                    (array) $get('selected_permission_ids'),
                                    (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())
                                )
                            );
                        })
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $set(
                                'selected_permission_ids',
                                static::mergeActiveModulePermissionSelection(
                                    (array) $get('selected_permission_ids'),
                                    is_array($state) ? $state : [],
                                    (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())
                                )
                            );
                        }),
                ])
                ->columnSpanFull(),

            Section::make('Security Confirmation')
                ->description('Confirm your password before creating roles or changing role permissions.')
                ->schema([
                    SensitiveActionPasswordConfirmation::passwordField(),
                ])
                ->columnSpanFull(),
        ]));
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('role_permission.manage');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess() && ! in_array($record->getAttribute('name'), ['super_admin', 'admin'], true);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions'),

                TextColumn::make('guard_name')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('permissions'))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    static::configureEditAction(EditAction::make())
                        ->modalWidth(width: 'full'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->modalWidth(width: 'full')
            ->using(function (array $data): Role {
                $role = Role::query()->create(static::roleAttributesFromData($data));
                $role->syncPermissions(static::selectedPermissionNamesFromData($data));

                return $role;
            });
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->fillForm(fn (Role $record): array => static::formDataForRole($record))
            ->using(function (Role $record, array $data): void {
                RoleEditProfiler::measure('role_edit_save', function () use ($record, $data): void {
                    $record->update(static::roleAttributesFromData($data));
                    $record->syncPermissions(static::selectedPermissionNamesFromData($data));
                });
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
            'view' => ViewRole::route('/{record}'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Security Summary')
                ->schema([
                    TextEntry::make('permissions_count')
                        ->label('Total Permissions')
                        ->state(fn (Role $record) => $record->permissions->count())
                        ->badge()
                        ->color('success'),

                    TextEntry::make('dangerous_permissions_count')
                        ->label('Dangerous Permissions')
                        ->state(fn (Role $record) => $record->permissions
                            ->pluck('name')
                            ->filter(fn ($permission) => static::isDangerousPermission($permission))
                            ->count()
                        )
                        ->badge()
                        ->color('danger'),
                ])
                ->columns(2),

            Section::make('Role Information')
                ->schema([
                    TextEntry::make('name')
                        ->badge()
                        ->color('primary'),

                    TextEntry::make('guard_name')
                        ->badge(),

                    TextEntry::make('permissions_count')
                        ->label('Assigned Permissions')
                        ->state(fn (Role $record) => $record->permissions->count())
                        ->badge()
                        ->color('success'),
                ])
                ->columns(3),

            Section::make('Dangerous Permissions')
                ->description('Permissions that can affect security, accounting, or system configuration.')
                ->visible(fn (Role $record) => $record->permissions
                    ->pluck('name')
                    ->contains(fn ($permission) => static::isDangerousPermission($permission)))
                ->schema([
                    TextEntry::make('dangerous_permissions')
                        ->state(function (Role $record) {
                            return $record->permissions
                                ->pluck('name')
                                ->filter(fn ($permission) => static::isDangerousPermission($permission))
                                ->all();
                        })
                        ->badge()
                        ->color('danger'),
                ]),

            Tabs::make('Permission Groups')
                ->tabs(static::assignedPermissionTabs())
                ->columnSpanFull(),
        ]);
    }

    /**
     * Generate tabs for the permission display in infolist only.
     *
     * @return array<Tab>
     */
    protected static function assignedPermissionTabs(): array
    {
        $groups = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($permission) => static::permissionGroupFor($permission->name));

        $tabs = [];

        foreach ($groups as $group => $permissions) {
            $tabs[] = Tab::make($group)
                ->schema([
                    TextEntry::make("group_{$group}")
                        ->state(function (Role $record) use ($permissions) {
                            return $record->permissions
                                ->pluck('name')
                                ->intersect($permissions->pluck('name'))
                                ->values()
                                ->all();
                        })
                        ->badge()
                        ->separator(',')
                        ->color('gray')
                        ->formatStateUsing(fn ($state) => static::permissionBadgeLabelFor($state)),
                ]);
        }

        return $tabs;
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    protected static function permissionBadgeGrid(array $permissionNames): Grid
    {
        return Grid::make(4)
            ->schema([
                TextEntry::make('permissions.name')
                    ->badge()
                    ->color(fn (string $state): string => static::isDangerousPermission($state) ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state): string => static::permissionBadgeLabelFor($state))
                    ->state(function (Model $record) use ($permissionNames): array {
                        return array_values(array_intersect(
                            $record->permissions->pluck('name')->all(),
                            $permissionNames
                        ));
                    }),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function permissionOptions(): array
    {
        return static::permissionCatalog()
            ->mapWithKeys(fn (array $permission): array => [
                $permission['id'] => $permission['label'],
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function permissionDescriptions(): array
    {
        return static::permissionCatalog()
            ->mapWithKeys(fn (array $permission): array => [
                $permission['id'] => $permission['description'],
            ])
            ->all();
    }

    public static function clearPermissionCatalog(): void
    {
        static::$permissionCatalog = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDataForRole(Role $role): array
    {
        return RoleEditProfiler::measure('role_selected_permissions_hydration', function () use ($role): array {
            $selectedPermissionIds = $role->permissions()
                ->pluck('permissions.id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->values()
                ->all();
            $activeModuleKey = static::defaultPermissionModuleKey();

            RoleEditProfiler::mark('role_hydration', [
                'role_id' => $role->id,
                'selected_permission_count' => count($selectedPermissionIds),
                'selected_permission_ids_json_bytes' => strlen(json_encode($selectedPermissionIds) ?: '[]'),
                'active_module' => $activeModuleKey,
            ]);

            return [
                ...Arr::only($role->attributesToArray(), ['name', 'guard_name']),
                'selected_permission_ids' => $selectedPermissionIds,
                'active_permission_module' => $activeModuleKey,
                'active_module_permission_ids' => static::activeModuleSelectedPermissionIds($selectedPermissionIds, $activeModuleKey),
            ];
        });
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function permissionGroupStateForRole(Role $role): array
    {
        $rolePermissionIds = $role->permissions()
            ->pluck('permissions.id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->all();

        $rolePermissionIdLookup = array_flip($rolePermissionIds);

        return static::permissionCatalog()
            ->groupBy('group_key')
            ->map(fn (Collection $permissions): array => $permissions
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => isset($rolePermissionIdLookup[$permissionId]))
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, guard_name: string}
     */
    public static function roleAttributesFromData(array $data): array
    {
        return [
            'name' => (string) $data['name'],
            'guard_name' => (string) ($data['guard_name'] ?? 'web'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function selectedPermissionNamesFromData(array $data): array
    {
        return RoleEditProfiler::measure('selected_permission_names_from_data', function () use ($data): array {
            $selectedPermissionIds = collect(
                array_key_exists('active_module_permission_ids', $data)
                    ? static::activeModulePermissionIdsFromData($data)
                    : ($data['selected_permission_ids'] ?? [])
            )
                ->merge(static::selectedPermissionIdsFromLegacyGroups($data))
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->unique()
                ->values();

            if ($selectedPermissionIds->isEmpty()) {
                RoleEditProfiler::mark('save_selection', ['selected_permission_count' => 0]);

                return [];
            }

            $selectedPermissionIdLookup = array_flip($selectedPermissionIds->all());

            $permissionNames = static::permissionCatalog()
                ->filter(fn (array $permission): bool => isset($selectedPermissionIdLookup[$permission['id']]))
                ->pluck('name')
                ->values()
                ->all();

            RoleEditProfiler::mark('save_selection', [
                'selected_permission_count' => count($permissionNames),
            ]);

            return $permissionNames;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    protected static function selectedPermissionIdsFromLegacyGroups(array $data): array
    {
        return collect($data['permission_groups'] ?? [])
            ->flatMap(fn ($permissionIds): array => is_array($permissionIds) ? $permissionIds : [])
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    protected static function activeModulePermissionIdsFromData(array $data): array
    {
        if (! array_key_exists('active_module_permission_ids', $data)) {
            return [];
        }

        return static::mergeActiveModulePermissionSelection(
            (array) ($data['selected_permission_ids'] ?? []),
            (array) $data['active_module_permission_ids'],
            (string) ($data['active_permission_module'] ?? static::defaultPermissionModuleKey())
        );
    }

    /**
     * @return array<string, string>
     */
    public static function permissionModuleOptions(): array
    {
        return RoleEditProfiler::measure('permission_module_options', fn (): array => static::permissionCatalog()
            ->groupBy('group_key')
            ->map(fn (Collection $permissions): string => static::permissionModuleOptionLabel($permissions))
            ->all());
    }

    public static function defaultPermissionModuleKey(): string
    {
        return (string) (static::permissionCatalog()->first()['group_key'] ?? 'system_setup');
    }

    public static function permissionModuleLabel(string $moduleKey): string
    {
        $permission = static::permissionCatalog()
            ->first(fn (array $permission): bool => $permission['group_key'] === $moduleKey);

        return $permission['group'] ?? str($moduleKey)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return array<int, string>
     */
    public static function activeModulePermissionOptions(string $moduleKey): array
    {
        return RoleEditProfiler::measure("active_module_permission_options:{$moduleKey}", function () use ($moduleKey): array {
            $permissions = static::permissionsForModule($moduleKey);

            $options = $permissions
                ->mapWithKeys(fn (array $permission): array => [
                    $permission['id'] => $permission['label'],
                ])
                ->all();

            RoleEditProfiler::mark('active_module', [
                'module' => $moduleKey,
                'permission_count' => $permissions->count(),
                'options_json_bytes' => strlen(json_encode($options) ?: '[]'),
            ]);

            return $options;
        });
    }

    /**
     * @return array<int, string>
     */
    public static function activeModulePermissionDescriptions(string $moduleKey): array
    {
        return RoleEditProfiler::measure("active_module_permission_descriptions:{$moduleKey}", fn (): array => static::permissionsForModule($moduleKey)
            ->mapWithKeys(fn (array $permission): array => [
                $permission['id'] => $permission['description'],
            ])
            ->all());
    }

    /**
     * @param  array<int, mixed>  $selectedPermissionIds
     * @return array<int, int>
     */
    public static function activeModuleSelectedPermissionIds(array $selectedPermissionIds, string $moduleKey): array
    {
        return RoleEditProfiler::measure("active_module_selected_permission_ids:{$moduleKey}", function () use ($selectedPermissionIds, $moduleKey): array {
            $selectedPermissionIdLookup = array_flip(array_map('intval', $selectedPermissionIds));

            return static::permissionsForModule($moduleKey)
                ->pluck('id')
                ->map(fn ($permissionId): int => (int) $permissionId)
                ->filter(fn (int $permissionId): bool => isset($selectedPermissionIdLookup[$permissionId]))
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<int, mixed>  $currentSelectedPermissionIds
     * @param  array<int, mixed>  $activeModuleSelectedPermissionIds
     * @return array<int, int>
     */
    public static function mergeActiveModulePermissionSelection(
        array $currentSelectedPermissionIds,
        array $activeModuleSelectedPermissionIds,
        string $moduleKey
    ): array {
        $activeModulePermissionIdLookup = array_flip(static::permissionsForModule($moduleKey)
            ->pluck('id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->all());

        return collect($currentSelectedPermissionIds)
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->reject(fn (int $permissionId): bool => isset($activeModulePermissionIdLookup[$permissionId]))
            ->merge(collect($activeModuleSelectedPermissionIds)->map(fn ($permissionId): int => (int) $permissionId))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $selectedPermissionIds
     */
    protected static function modulePermissionCountsHtml(array $selectedPermissionIds): HtmlString
    {
        return RoleEditProfiler::measure('module_permission_counts_html', function () use ($selectedPermissionIds): HtmlString {
            $selectedPermissionIdLookup = array_flip(array_map('intval', $selectedPermissionIds));

            $html = static::permissionCatalog()
                ->groupBy('group_key')
                ->map(function (Collection $permissions) use ($selectedPermissionIdLookup): string {
                    $selectedCount = $permissions
                        ->pluck('id')
                        ->filter(fn ($permissionId): bool => isset($selectedPermissionIdLookup[(int) $permissionId]))
                        ->count();

                    return e($permissions->first()['group']).': '.$selectedCount.'/'.$permissions->count();
                })
                ->implode(' · ');

            return new HtmlString('<span class="text-sm text-gray-600 dark:text-gray-300">'.$html.'</span>');
        });
    }

    protected static function permissionModuleOptionLabel(Collection $permissions): string
    {
        return $permissions->first()['group'].' ('.$permissions->count().')';
    }

    /**
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, label: string, description: string}>
     */
    public static function permissionsForModule(string $moduleKey): Collection
    {
        return static::permissionCatalog()
            ->filter(fn (array $permission): bool => $permission['group_key'] === $moduleKey)
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, label: string, description: string}>
     */
    protected static function permissionCatalog(): Collection
    {
        if (static::$permissionCatalog instanceof Collection) {
            return static::$permissionCatalog;
        }

        static::$permissionCatalog = RoleEditProfiler::measure('permission_catalog_load', function (): Collection {
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function (Permission $permission): array {
                    $group = static::permissionGroupFor($permission->name);

                    return [
                        'id' => (int) $permission->id,
                        'name' => $permission->name,
                        'group' => $group,
                        'group_key' => str($group)->slug('_')->toString(),
                        'label' => static::permissionLabelFor($permission->name),
                        'description' => static::isDangerousPermission($permission->name)
                            ? 'Dangerous permission. Grant only to trusted administrators.'
                            : $permission->name,
                    ];
                });

            RoleEditProfiler::mark('permission_catalog', [
                'permission_count' => $permissions->count(),
                'module_count' => $permissions->pluck('group_key')->unique()->count(),
                'module_counts' => $permissions->groupBy('group_key')->map->count()->all(),
            ]);

            return $permissions;
        });

        return static::$permissionCatalog;
    }

    public static function permissionGroupFor(string $permission): string
    {
        return match (true) {
            str_starts_with($permission, 'factory.') => 'Manufacturing',
            str_starts_with($permission, 'warehouse.') => 'Warehouse',
            str_starts_with($permission, 'sales.') => 'Sales',
            str_starts_with($permission, 'procurement.') => 'Procurement',
            str_starts_with($permission, 'finance.') => 'Finance',
            str_starts_with($permission, 'hr.') => 'Human Resources',
            str_starts_with($permission, 'payroll.') => 'Payroll',
            str_starts_with($permission, 'fixed_asset.') => 'Fixed Assets',
            str_contains($permission, ':') => 'Legacy',
            str_contains($permission, 'report') => 'Reports',
            str_starts_with($permission, 'audit_trail.') => 'Audit Trail',
            str_contains($permission, 'role')
            || str_contains($permission, 'user')
            || str_contains($permission, 'permission') => 'Security',
            default => 'System Setup',
        };
    }

    public static function permissionLabelFor(string $permission): string
    {
        $danger = static::isDangerousPermission($permission)
            ? '<strong class="text-danger-600">[DANGEROUS]</strong> '
            : '';

        $group = static::permissionGroupFor($permission);

        $humanName = str($permission)
            ->replace(['view_any', 'view:any', ':', '.', '_'], ['View List', 'View List', ' ', ' ', ' '])
            ->headline()
            ->toString();

        return "{$danger}<strong>{$group}:</strong> {$humanName} <span class=\"text-xs text-gray-500\">({$permission})</span>";
    }

    public static function permissionBadgeLabelFor(string $permission): string
    {
        $danger = static::isDangerousPermission($permission) ? '⚠ ' : '';

        $humanName = str($permission)
            ->replace(['view_any', 'view:any', ':', '.', '_'], ['View List', 'View List', ' ', ' ', ' '])
            ->headline()
            ->toString();

        return "{$danger}{$humanName}";
    }

    public static function isDangerousPermission(string $permission): bool
    {
        return in_array($permission, [
            'role_permission.manage',
            'user.manage',
            'user_management_access',
            'number_series.manage',
            'posting_setup.manage',
            'chart_of_account.manage',
            'audit_trail.view_any',
            'audit_trail.view',
        ], true);
    }
}
