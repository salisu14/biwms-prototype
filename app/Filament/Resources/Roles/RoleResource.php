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
     * @var Collection<int, array{id: int, name: string, group: string, group_key: string, resource: string, resource_key: string, action: string, label: string, description: string}>|null
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
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ]),

            Section::make('Permissions')
                ->description('Select a module to load and edit only that module’s permissions.')
                ->schema([
                    Hidden::make('selected_permission_ids')
                        ->default([])
                        ->dehydrated(),

                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                        ->schema([
                            Select::make('active_permission_module')
                                ->label('Permission module')
                                ->options(static::permissionModuleOptions())
                                ->default(static::defaultPermissionModuleKey())
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    $moduleKey = $state ?: static::defaultPermissionModuleKey();
                                    $resourceGroupKey = static::defaultPermissionResourceGroupKey($moduleKey);

                                    $set('active_permission_search', null);
                                    $set('active_permission_resource_group', $resourceGroupKey);
                                    $set(
                                        'active_group_permission_ids',
                                        static::activeGroupSelectedPermissionIds(
                                            (array) $get('selected_permission_ids'),
                                            $moduleKey,
                                            $resourceGroupKey
                                        )
                                    );
                                })
                                ->helperText('Only one module is loaded at a time.'),

                            TextInput::make('active_permission_search')
                                ->label('Search')
                                ->placeholder('Find resources or permissions')
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    $moduleKey = (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey());
                                    $resourceGroupKey = static::defaultPermissionResourceGroupKey($moduleKey, $state);

                                    $set('active_permission_resource_group', $resourceGroupKey);
                                    $set(
                                        'active_group_permission_ids',
                                        static::activeGroupSelectedPermissionIds(
                                            (array) $get('selected_permission_ids'),
                                            $moduleKey,
                                            $resourceGroupKey,
                                            $state
                                        )
                                    );
                                }),

                            Select::make('active_permission_resource_group')
                                ->label('Resource group')
                                ->options(fn (Get $get): array => static::permissionResourceGroupOptions(
                                    (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()),
                                    $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                                ))
                                ->default(fn (Get $get): string => static::defaultPermissionResourceGroupKey(
                                    (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()),
                                    $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                                ))
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    $moduleKey = (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey());
                                    $resourceGroupKey = $state ?: static::defaultPermissionResourceGroupKey($moduleKey);

                                    $set(
                                        'active_group_permission_ids',
                                        static::activeGroupSelectedPermissionIds(
                                            (array) $get('selected_permission_ids'),
                                            $moduleKey,
                                            $resourceGroupKey,
                                            $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                                        )
                                    );
                                })
                                ->helperText('Large modules are split by resource.'),
                        ]),

                    Placeholder::make('module_permission_counts')
                        ->label('Selected permissions')
                        ->content(fn (Get $get): HtmlString => static::modulePermissionCountsHtml((array) $get('selected_permission_ids'))),

                    CheckboxList::make('active_group_permission_ids')
                        ->label(fn (Get $get): string => static::permissionResourceGroupLabel(
                            (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()),
                            (string) ($get('active_permission_resource_group') ?: static::defaultPermissionResourceGroupKey((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey())))
                        ))
                        ->options(fn (Get $get): array => static::activeGroupPermissionOptions(
                            (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()),
                            (string) ($get('active_permission_resource_group') ?: static::defaultPermissionResourceGroupKey((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()))),
                            $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                        ))
                        ->descriptions(fn (Get $get): array => static::activeGroupPermissionDescriptions(
                            (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()),
                            (string) ($get('active_permission_resource_group') ?: static::defaultPermissionResourceGroupKey((string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey()))),
                            $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                        ))
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->bulkToggleable()
                        ->allowHtml()
                        ->live()
                        ->afterStateHydrated(function (Set $set, Get $get): void {
                            $moduleKey = (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey());
                            $resourceGroupKey = (string) ($get('active_permission_resource_group') ?: static::defaultPermissionResourceGroupKey($moduleKey));

                            $set(
                                'active_group_permission_ids',
                                static::activeGroupSelectedPermissionIds(
                                    (array) $get('selected_permission_ids'),
                                    $moduleKey,
                                    $resourceGroupKey,
                                    $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
                                )
                            );
                        })
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $moduleKey = (string) ($get('active_permission_module') ?: static::defaultPermissionModuleKey());
                            $resourceGroupKey = (string) ($get('active_permission_resource_group') ?: static::defaultPermissionResourceGroupKey($moduleKey));

                            $set(
                                'selected_permission_ids',
                                static::mergeActiveGroupPermissionSelection(
                                    (array) $get('selected_permission_ids'),
                                    is_array($state) ? $state : [],
                                    $moduleKey,
                                    $resourceGroupKey,
                                    $get('active_permission_search') === null ? null : (string) $get('active_permission_search')
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
                'active_permission_search' => null,
                'active_permission_resource_group' => static::defaultPermissionResourceGroupKey($activeModuleKey),
                'active_group_permission_ids' => static::activeGroupSelectedPermissionIds(
                    $selectedPermissionIds,
                    $activeModuleKey,
                    static::defaultPermissionResourceGroupKey($activeModuleKey)
                ),
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
                array_key_exists('active_group_permission_ids', $data)
                    ? static::activeGroupPermissionIdsFromData($data)
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
    protected static function activeGroupPermissionIdsFromData(array $data): array
    {
        if (! array_key_exists('active_group_permission_ids', $data)) {
            return [];
        }

        $moduleKey = (string) ($data['active_permission_module'] ?? static::defaultPermissionModuleKey());

        return static::mergeActiveGroupPermissionSelection(
            (array) ($data['selected_permission_ids'] ?? []),
            (array) $data['active_group_permission_ids'],
            $moduleKey,
            (string) ($data['active_permission_resource_group'] ?? static::defaultPermissionResourceGroupKey($moduleKey)),
            $data['active_permission_search'] ?? null
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
        return static::activeGroupPermissionOptions($moduleKey, static::defaultPermissionResourceGroupKey($moduleKey));
    }

    /**
     * @return array<int, string>
     */
    public static function activeGroupPermissionOptions(string $moduleKey, string $resourceGroupKey, ?string $search = null): array
    {
        return RoleEditProfiler::measure("active_group_permission_options:{$moduleKey}:{$resourceGroupKey}", function () use ($moduleKey, $resourceGroupKey, $search): array {
            $permissions = static::permissionsForResourceGroup($moduleKey, $resourceGroupKey, $search);

            $options = $permissions
                ->mapWithKeys(fn (array $permission): array => [
                    $permission['id'] => $permission['label'],
                ])
                ->all();

            RoleEditProfiler::mark('active_module', [
                'module' => $moduleKey,
                'resource_group' => $resourceGroupKey,
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
        return static::activeGroupPermissionDescriptions($moduleKey, static::defaultPermissionResourceGroupKey($moduleKey));
    }

    /**
     * @return array<int, string>
     */
    public static function activeGroupPermissionDescriptions(string $moduleKey, string $resourceGroupKey, ?string $search = null): array
    {
        return RoleEditProfiler::measure("active_group_permission_descriptions:{$moduleKey}:{$resourceGroupKey}", fn (): array => static::permissionsForResourceGroup($moduleKey, $resourceGroupKey, $search)
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
        return static::activeGroupSelectedPermissionIds(
            $selectedPermissionIds,
            $moduleKey,
            static::defaultPermissionResourceGroupKey($moduleKey)
        );
    }

    /**
     * @param  array<int, mixed>  $selectedPermissionIds
     * @return array<int, int>
     */
    public static function activeGroupSelectedPermissionIds(array $selectedPermissionIds, string $moduleKey, string $resourceGroupKey, ?string $search = null): array
    {
        return RoleEditProfiler::measure("active_group_selected_permission_ids:{$moduleKey}:{$resourceGroupKey}", function () use ($selectedPermissionIds, $moduleKey, $resourceGroupKey, $search): array {
            $selectedPermissionIdLookup = array_flip(array_map('intval', $selectedPermissionIds));

            return static::permissionsForResourceGroup($moduleKey, $resourceGroupKey, $search)
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
        return static::mergeActiveGroupPermissionSelection(
            $currentSelectedPermissionIds,
            $activeModuleSelectedPermissionIds,
            $moduleKey,
            static::defaultPermissionResourceGroupKey($moduleKey)
        );
    }

    /**
     * @param  array<int, mixed>  $currentSelectedPermissionIds
     * @param  array<int, mixed>  $activeGroupSelectedPermissionIds
     * @return array<int, int>
     */
    public static function mergeActiveGroupPermissionSelection(
        array $currentSelectedPermissionIds,
        array $activeGroupSelectedPermissionIds,
        string $moduleKey,
        string $resourceGroupKey,
        ?string $search = null
    ): array {
        $activeGroupPermissionIdLookup = array_flip(static::permissionsForResourceGroup($moduleKey, $resourceGroupKey, $search)
            ->pluck('id')
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->all());

        return collect($currentSelectedPermissionIds)
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->reject(fn (int $permissionId): bool => isset($activeGroupPermissionIdLookup[$permissionId]))
            ->merge(collect($activeGroupSelectedPermissionIds)->map(fn ($permissionId): int => (int) $permissionId))
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
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, resource: string, resource_key: string, action: string, label: string, description: string}>
     */
    public static function permissionsForModule(string $moduleKey): Collection
    {
        return static::permissionCatalog()
            ->filter(fn (array $permission): bool => $permission['group_key'] === $moduleKey)
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public static function permissionResourceGroupOptions(string $moduleKey, ?string $search = null): array
    {
        return static::permissionsForModule($moduleKey)
            ->filter(fn (array $permission): bool => static::permissionMatchesSearch($permission, $search))
            ->groupBy('resource_key')
            ->map(fn (Collection $permissions): string => static::permissionResourceGroupOptionLabel($permissions))
            ->all();
    }

    public static function defaultPermissionResourceGroupKey(string $moduleKey, ?string $search = null): string
    {
        $resourceGroupOptions = static::permissionResourceGroupOptions($moduleKey, $search);

        return (string) array_key_first($resourceGroupOptions)
            ?: (string) (static::permissionsForModule($moduleKey)->first()['resource_key'] ?? 'general');
    }

    public static function permissionResourceGroupLabel(string $moduleKey, string $resourceGroupKey): string
    {
        $permission = static::permissionsForModule($moduleKey)
            ->first(fn (array $permission): bool => $permission['resource_key'] === $resourceGroupKey);

        return $permission['resource'] ?? str($resourceGroupKey)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, resource: string, resource_key: string, action: string, label: string, description: string}>
     */
    public static function permissionsForResourceGroup(string $moduleKey, string $resourceGroupKey, ?string $search = null): Collection
    {
        return static::permissionsForModule($moduleKey)
            ->filter(fn (array $permission): bool => $permission['resource_key'] === $resourceGroupKey)
            ->filter(fn (array $permission): bool => static::permissionMatchesSearch($permission, $search))
            ->values();
    }

    protected static function permissionResourceGroupOptionLabel(Collection $permissions): string
    {
        return $permissions->first()['resource'].' ('.$permissions->count().')';
    }

    /**
     * @param  array{id: int, name: string, group: string, group_key: string, resource: string, resource_key: string, action: string, label: string, description: string}  $permission
     */
    protected static function permissionMatchesSearch(array $permission, ?string $search): bool
    {
        $search = strtolower(trim((string) $search));

        if ($search === '') {
            return true;
        }

        return str_contains(strtolower($permission['name']), $search)
            || str_contains(strtolower($permission['resource']), $search)
            || str_contains(strtolower($permission['action']), $search);
    }

    /**
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, resource: string, resource_key: string, action: string, label: string, description: string}>
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
                    $resourceKey = static::permissionResourceKeyFor($permission->name);
                    $action = static::permissionActionFor($permission->name);

                    return [
                        'id' => (int) $permission->id,
                        'name' => $permission->name,
                        'group' => $group,
                        'group_key' => str($group)->slug('_')->toString(),
                        'resource' => str($resourceKey)->replace('_', ' ')->headline()->toString(),
                        'resource_key' => $resourceKey,
                        'action' => $action,
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
                'largest_resource_group_count' => $permissions
                    ->groupBy(fn (array $permission): string => $permission['group_key'].':'.$permission['resource_key'])
                    ->map->count()
                    ->max(),
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

    public static function permissionResourceKeyFor(string $permission): string
    {
        $segments = static::permissionNameSegments($permission);

        if (count($segments) >= 3 && in_array($segments[0], [
            'admin',
            'audit_trail',
            'factory',
            'finance',
            'fixed_asset',
            'hr',
            'payroll',
            'procurement',
            'sales',
            'warehouse',
        ], true)) {
            return $segments[1];
        }

        if (count($segments) >= 2) {
            return implode('_', array_slice($segments, 0, -1));
        }

        return $segments[0] ?? 'general';
    }

    public static function permissionActionFor(string $permission): string
    {
        $segments = static::permissionNameSegments($permission);

        return end($segments) ?: 'manage';
    }

    /**
     * @return array<int, string>
     */
    protected static function permissionNameSegments(string $permission): array
    {
        return array_values(array_filter(
            explode('.', str_replace(':', '.', $permission)),
            fn (string $segment): bool => $segment !== ''
        ));
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
