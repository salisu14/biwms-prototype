<?php

namespace App\Filament\Resources\DocumentHeaders;

use App\Filament\Resources\DocumentHeaders\Pages\CreateDocumentHeader;
use App\Filament\Resources\DocumentHeaders\Pages\EditDocumentHeader;
use App\Filament\Resources\DocumentHeaders\Pages\ListDocumentHeaders;
use App\Filament\Resources\DocumentHeaders\Pages\ViewDocumentHeader;
use App\Filament\Resources\DocumentHeaders\Schemas\DocumentHeaderForm;
use App\Filament\Resources\DocumentHeaders\Schemas\DocumentHeaderInfolist;
use App\Filament\Resources\DocumentHeaders\Tables\DocumentHeadersTable;
use App\Models\DocumentHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentHeaderResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'document_headers';
    }

    public static function permissionResource(): string
    {
        return 'document_header';
    }

    protected static ?string $model = DocumentHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DocumentHeaderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentHeaderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentHeadersTable::configure($table);
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
            'index' => ListDocumentHeaders::route('/'),
            'create' => CreateDocumentHeader::route('/create'),
            'view' => ViewDocumentHeader::route('/{record}'),
            'edit' => EditDocumentHeader::route('/{record}/edit'),
        ];
    }
}
