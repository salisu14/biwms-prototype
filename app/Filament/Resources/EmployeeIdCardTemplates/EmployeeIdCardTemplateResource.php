<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardTemplates;

use App\Filament\Resources\EmployeeIdCardTemplates\Pages\CreateEmployeeIdCardTemplate;
use App\Filament\Resources\EmployeeIdCardTemplates\Pages\EditEmployeeIdCardTemplate;
use App\Filament\Resources\EmployeeIdCardTemplates\Pages\ListEmployeeIdCardTemplates;
use App\Models\EmployeeIdCardTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeIdCardTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_id_card_template';
    }

    protected static ?string $model = EmployeeIdCardTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Identity';

    protected static ?string $navigationLabel = 'Card Templates';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')
                ->columns(3)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('orientation')
                        ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
                        ->default('portrait')
                        ->required(),
                    Toggle::make('is_active')->default(true),
                    TextInput::make('width_mm')->numeric()->default(85.60)->required(),
                    TextInput::make('height_mm')->numeric()->default(53.98)->required(),
                    Toggle::make('is_default')->default(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('orientation')->badge(),
                TextColumn::make('width_mm')->label('Width mm')->numeric(),
                TextColumn::make('height_mm')->label('Height mm')->numeric(),
                IconColumn::make('is_default')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeIdCardTemplates::route('/'),
            'create' => CreateEmployeeIdCardTemplate::route('/create'),
            'edit' => EditEmployeeIdCardTemplate::route('/{record}/edit'),
        ];
    }
}
