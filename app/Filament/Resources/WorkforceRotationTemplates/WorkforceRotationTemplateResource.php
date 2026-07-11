<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates;

use App\Filament\Resources\WorkforceRotationTemplates\Pages\CreateWorkforceRotationTemplate;
use App\Filament\Resources\WorkforceRotationTemplates\Pages\EditWorkforceRotationTemplate;
use App\Filament\Resources\WorkforceRotationTemplates\Pages\ListWorkforceRotationTemplates;
use App\Filament\Resources\WorkforceRotationTemplates\Pages\ViewWorkforceRotationTemplate;
use App\Filament\Resources\WorkforceRotationTemplates\Schemas\WorkforceRotationTemplateForm;
use App\Filament\Resources\WorkforceRotationTemplates\Schemas\WorkforceRotationTemplateInfolist;
use App\Filament\Resources\WorkforceRotationTemplates\Tables\WorkforceRotationTemplatesTable;
use App\Models\WorkforceRotationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRotationTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_rotation_template';
    }

    protected static ?string $model = WorkforceRotationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Rotation Templates';

    public static function form(Schema $schema): Schema
    {
        return WorkforceRotationTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRotationTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRotationTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkforceRotationTemplates::route('/'),
            'create' => CreateWorkforceRotationTemplate::route('/create'),
            'view' => ViewWorkforceRotationTemplate::route('/{record}'),
            'edit' => EditWorkforceRotationTemplate::route('/{record}/edit'),
        ];
    }
}
