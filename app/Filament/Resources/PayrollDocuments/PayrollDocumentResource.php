<?php

namespace App\Filament\Resources\PayrollDocuments;

use App\Filament\Resources\PayrollDocuments\Pages\CreatePayrollDocument;
use App\Filament\Resources\PayrollDocuments\Pages\EditPayrollDocument;
use App\Filament\Resources\PayrollDocuments\Pages\ListPayrollDocuments;
use App\Filament\Resources\PayrollDocuments\Schemas\PayrollDocumentForm;
use App\Filament\Resources\PayrollDocuments\Tables\PayrollDocumentsTable;
use App\Models\PayrollDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayrollDocumentResource extends Resource
{
    protected static ?string $model = PayrollDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PayrollDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollDocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
//            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollDocuments::route('/'),
            'create' => CreatePayrollDocument::route('/create'),
            'edit' => EditPayrollDocument::route('/{record}/edit'),
        ];
    }
}
