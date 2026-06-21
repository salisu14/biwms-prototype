<?php

namespace App\Filament\Resources\CapExProjects;

use App\Filament\Resources\CapExProjects\Pages\CreateCapExProject;
use App\Filament\Resources\CapExProjects\Pages\EditCapExProject;
use App\Filament\Resources\CapExProjects\Pages\ListCapExProjects;
use App\Filament\Resources\CapExProjects\Pages\ViewCapExProject;
use App\Filament\Resources\CapExProjects\Schemas\CapExProjectForm;
use App\Filament\Resources\CapExProjects\Schemas\CapExProjectInfolist;
use App\Filament\Resources\CapExProjects\Tables\CapExProjectsTable;
use App\Models\Manufacturing\CapExProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CapExProjectResource extends Resource
{
    protected static ?string $model = CapExProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $slug = 'capex-projects';

    public static function form(Schema $schema): Schema
    {
        return CapExProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CapExProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapExProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'project_number',
            'description',
            'status',
            'projectManager.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var CapExProject $record */
        $projectNumber = $record->project_number ?: 'Unknown Project';
        $description = $record->description ?: 'No description';

        return "{$projectNumber} - {$description}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CapExProject $record */
        return [
            'Status' => $record->status ?: '—',
            'Project Manager' => $record->projectManager?->name ?: '—',
            'Budget' => number_format((float) $record->budget_amount, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with('projectManager')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapExProjects::route('/'),
            'create' => CreateCapExProject::route('/create'),
            'view' => ViewCapExProject::route('/{record}'),
            'edit' => EditCapExProject::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
