<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveTypes;

use App\Filament\Resources\LeaveTypes\Pages\CreateLeaveType;
use App\Filament\Resources\LeaveTypes\Pages\EditLeaveType;
use App\Filament\Resources\LeaveTypes\Pages\ListLeaveTypes;
use App\Models\LeaveType;
use BackedEnum;
use Filament\Actions\EditAction;
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

class LeaveTypeResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'leave_type';
    }

    protected static ?string $model = LeaveType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Leave Management';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave Type')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    TextInput::make('code')->required()->maxLength(50),
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('unit')->options(['days' => 'Days', 'hours' => 'Hours'])->default('days')->required(),
                    Textarea::make('description')->columnSpanFull(),
                    Toggle::make('paid')->default(true),
                    Toggle::make('requires_attachment'),
                    TextInput::make('attachment_required_after_days')->numeric(),
                    Toggle::make('allow_half_day')->default(true),
                    Toggle::make('allow_negative_balance'),
                    Toggle::make('requires_manager_approval')->default(true),
                    Toggle::make('requires_hr_approval')->default(true),
                    TextInput::make('color')->maxLength(30),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('unit')->badge(),
                IconColumn::make('paid')->boolean(),
                IconColumn::make('allow_negative_balance')->boolean()->toggleable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveTypes::route('/'),
            'create' => CreateLeaveType::route('/create'),
            'edit' => EditLeaveType::route('/{record}/edit'),
        ];
    }
}
