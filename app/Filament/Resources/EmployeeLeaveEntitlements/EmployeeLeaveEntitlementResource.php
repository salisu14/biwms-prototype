<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveEntitlements;

use App\Filament\Resources\EmployeeLeaveEntitlements\Pages\CreateEmployeeLeaveEntitlement;
use App\Filament\Resources\EmployeeLeaveEntitlements\Pages\EditEmployeeLeaveEntitlement;
use App\Filament\Resources\EmployeeLeaveEntitlements\Pages\ListEmployeeLeaveEntitlements;
use App\Models\Employee;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeLeaveEntitlementResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'leave_entitlement';
    }

    protected static ?string $model = EmployeeLeaveEntitlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entitlement')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Select::make('employee_id')->options(fn (): array => Employee::query()->orderBy('employee_number')->pluck('full_name', 'id')->all())->searchable()->required(),
                    Select::make('leave_type_id')->options(fn (): array => LeaveType::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                    Select::make('leave_policy_id')->options(fn (): array => LeavePolicy::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    TextInput::make('leave_year')->numeric()->default((int) now()->year)->required(),
                    TextInput::make('opening_balance')->numeric()->default(0)->required(),
                    TextInput::make('entitled_amount')->numeric()->default(0)->required(),
                    TextInput::make('carried_forward')->numeric()->default(0)->required(),
                    DatePicker::make('expires_at'),
                    Select::make('status')->options(['active' => 'Active', 'expired' => 'Expired', 'closed' => 'Closed'])->default('active')->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('leaveType.name')->label('Leave Type')->searchable(),
                TextColumn::make('leave_year')->sortable(),
                TextColumn::make('entitled_amount')->numeric(),
                TextColumn::make('opening_balance')->numeric()->toggleable(),
                TextColumn::make('carried_forward')->numeric()->toggleable(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeLeaveEntitlements::route('/'),
            'create' => CreateEmployeeLeaveEntitlement::route('/create'),
            'edit' => EditEmployeeLeaveEntitlement::route('/{record}/edit'),
        ];
    }
}
