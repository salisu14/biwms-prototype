<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates;

use App\Filament\Resources\PerformanceAppraisalTemplates\Pages\CreatePerformanceAppraisalTemplate;
use App\Filament\Resources\PerformanceAppraisalTemplates\Pages\EditPerformanceAppraisalTemplate;
use App\Filament\Resources\PerformanceAppraisalTemplates\Pages\ListPerformanceAppraisalTemplates;
use App\Filament\Resources\PerformanceAppraisalTemplates\Pages\ViewPerformanceAppraisalTemplate;
use App\Filament\Resources\PerformanceAppraisalTemplates\Schemas\PerformanceAppraisalTemplateForm;
use App\Filament\Resources\PerformanceAppraisalTemplates\Schemas\PerformanceAppraisalTemplateInfolist;
use App\Filament\Resources\PerformanceAppraisalTemplates\Tables\PerformanceAppraisalTemplatesTable;
use App\Models\PerformanceAppraisalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_appraisal_template';
    }

    protected static ?string $model = PerformanceAppraisalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalTemplatesTable::configure($table);
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
            'index' => ListPerformanceAppraisalTemplates::route('/'),
            'create' => CreatePerformanceAppraisalTemplate::route('/create'),
            'view' => ViewPerformanceAppraisalTemplate::route('/{record}'),
            'edit' => EditPerformanceAppraisalTemplate::route('/{record}/edit'),
        ];
    }
}
