<?php

namespace App\Filament\Resources\ApprovalTemplates;

use App\Filament\Resources\ApprovalTemplates\Pages\CreateApprovalTemplate;
use App\Filament\Resources\ApprovalTemplates\Pages\EditApprovalTemplate;
use App\Filament\Resources\ApprovalTemplates\Pages\ListApprovalTemplates;
use App\Filament\Resources\ApprovalTemplates\Pages\ViewApprovalTemplate;
use App\Filament\Resources\ApprovalTemplates\Schemas\ApprovalTemplateForm;
use App\Filament\Resources\ApprovalTemplates\Schemas\ApprovalTemplateInfolist;
use App\Filament\Resources\ApprovalTemplates\Tables\ApprovalTemplatesTable;
use App\Models\ApprovalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApprovalTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'approval_templates';
    }

    public static function permissionResource(): string
    {
        return 'approval_template';
    }

    protected static ?string $model = ApprovalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ApprovalTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApprovalTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalTemplates::route('/'),
            'create' => CreateApprovalTemplate::route('/create'),
            'view' => ViewApprovalTemplate::route('/{record}'),
            'edit' => EditApprovalTemplate::route('/{record}/edit'),
        ];
    }
}
