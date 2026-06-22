<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Models\Permission;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
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

class RoleResource extends Resource
{
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
                ->description('Use search to find permissions. Dangerous permissions are marked.')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('')
                        ->relationship('permissions', 'name')
                        ->options(static::permissionOptions())
                        ->descriptions(static::permissionDescriptions())
                        ->columns(4)
                        ->bulkToggleable()
                        ->allowHtml()
                        ->searchable()
                        ->helperText('Only one permission selector is used to avoid invalid selections across grouped tabs.'),
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
                    EditAction::make()
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
            Section::make('Role Information')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('guard_name'),
                ]),

            Tabs::make('Permissions')
                ->tabs(static::permissionInfolistTabs())
                ->columnSpanFull()
                ->contained(false),
        ]);
    }

    /**
     * Generate tabs for the permission display in infolist only.
     *
     * @return array<Tab>
     */
    protected static function permissionInfolistTabs(): array
    {
        $grouped = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => static::permissionGroupFor($permission->name))
            ->sortKeys();

        $tabs = [];

        $allPermissions = $grouped->flatten();

        $tabs[] = Tab::make('All Permissions')
            ->badge($allPermissions->count())
            ->schema([
                static::permissionBadgeGrid($allPermissions->pluck('name')->all()),
            ]);

        foreach ($grouped as $group => $permissions) {
            $tabs[] = Tab::make($group)
                ->badge($permissions->count())
                ->schema([
                    static::permissionBadgeGrid($permissions->pluck('name')->all()),
                ]);
        }

        return $tabs;
    }

    /**
     * @param array<int, string> $permissionNames
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
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => static::permissionLabelFor($permission->name),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function permissionDescriptions(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => static::isDangerousPermission($permission->name)
                    ? 'Dangerous permission. Grant only to trusted administrators.'
                    : $permission->name,
            ])
            ->all();
    }

    public static function permissionGroupFor(string $permission): string
    {
        return match (true) {
            str_starts_with($permission, 'factory.') => 'Manufacturing',
            str_starts_with($permission, 'warehouse.') => 'Warehouse',
            str_starts_with($permission, 'sales.item') || str_contains($permission, 'item') => 'Inventory',
            str_starts_with($permission, 'sales.') => 'Sales',
            str_starts_with($permission, 'procurement.') => 'Purchase',
            str_starts_with($permission, 'finance.') || str_contains($permission, 'bank') || str_contains($permission, 'payment') => 'Finance',
            str_starts_with($permission, 'hr.') || str_starts_with($permission, 'payroll.') => 'Payroll',
            str_starts_with($permission, 'fixed_asset.') || str_contains($permission, 'fa_') => 'Fixed Assets',
            str_contains($permission, 'report') => 'Reports',
            str_contains($permission, 'setup') || str_contains($permission, 'posting_group') || str_contains($permission, 'number_series') => 'Settings',
            str_contains($permission, 'role') || str_contains($permission, 'user') || str_contains($permission, 'permission') => 'Security',
            str_starts_with($permission, 'audit_trail.') => 'Audit',
            str_contains($permission, ':')
            || str_ends_with($permission, '_access')
            || str_ends_with($permission, '_show')
            || str_ends_with($permission, '_create')
            || str_ends_with($permission, '_edit')
            || str_ends_with($permission, '_delete') => 'Legacy',
            default => 'Settings',
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
