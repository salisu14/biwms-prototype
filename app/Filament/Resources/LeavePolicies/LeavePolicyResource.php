<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeavePolicies;

use App\Filament\Resources\LeavePolicies\Pages\CreateLeavePolicy;
use App\Filament\Resources\LeavePolicies\Pages\EditLeavePolicy;
use App\Filament\Resources\LeavePolicies\Pages\ListLeavePolicies;
use App\Models\Department;
use App\Models\LeavePolicy;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeavePolicyResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'leave_policy';
    }

    protected static ?string $model = LeavePolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Policy')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('department_id')->label('Department')->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    DatePicker::make('effective_from')->required()->default(now()),
                    DatePicker::make('effective_to'),
                    TextInput::make('assignment_type')->maxLength(50),
                    Toggle::make('is_default'),
                    Toggle::make('is_active')->default(true),
                    Textarea::make('description')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('department.name')->label('Department')->toggleable(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->toggleable(),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeavePolicies::route('/'),
            'create' => CreateLeavePolicy::route('/create'),
            'edit' => EditLeavePolicy::route('/{record}/edit'),
        ];
    }
}
