<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
        return $schema->components([
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
                ->description('Permissions are grouped by module to keep role editing responsive in large installations.')
                ->schema([
                    Tabs::make('Permission Modules')
                        ->tabs(static::permissionFormTabs())
                        ->persistTab(),
                ])
                ->columnSpanFull(),

            Section::make('Security Confirmation')
                ->description('Confirm your password before creating roles or changing role permissions.')
                ->schema([
                    SensitiveActionPasswordConfirmation::passwordField(),
                ])
                ->columnSpanFull(),
        ]);
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
                $record->update(static::roleAttributesFromData($data));
                $record->syncPermissions(static::selectedPermissionNamesFromData($data));
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
        return [
            ...Arr::only($role->attributesToArray(), ['name', 'guard_name']),
            'permission_groups' => static::permissionGroupStateForRole($role),
        ];
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
        $selectedPermissionIds = collect($data['permission_groups'] ?? [])
            ->flatMap(fn ($permissionIds): array => is_array($permissionIds) ? $permissionIds : [])
            ->map(fn ($permissionId): int => (int) $permissionId)
            ->unique()
            ->values();

        if ($selectedPermissionIds->isEmpty()) {
            return [];
        }

        $selectedPermissionIdLookup = array_flip($selectedPermissionIds->all());

        return static::permissionCatalog()
            ->filter(fn (array $permission): bool => isset($selectedPermissionIdLookup[$permission['id']]))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, Tab>
     */
    protected static function permissionFormTabs(): array
    {
        return static::permissionCatalog()
            ->groupBy('group')
            ->map(function (Collection $permissions, string $group): Tab {
                $groupKey = (string) $permissions->first()['group_key'];

                return Tab::make($group)
                    ->schema([
                        CheckboxList::make("permission_groups.{$groupKey}")
                            ->label($group)
                            ->options($permissions->mapWithKeys(fn (array $permission): array => [
                                $permission['id'] => $permission['label'],
                            ])->all())
                            ->descriptions($permissions->mapWithKeys(fn (array $permission): array => [
                                $permission['id'] => $permission['description'],
                            ])->all())
                            ->columns(3)
                            ->bulkToggleable()
                            ->allowHtml()
                            ->searchable(),
                    ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string, group: string, group_key: string, label: string, description: string}>
     */
    protected static function permissionCatalog(): Collection
    {
        if (static::$permissionCatalog instanceof Collection) {
            return static::$permissionCatalog;
        }

        static::$permissionCatalog = Permission::query()
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
