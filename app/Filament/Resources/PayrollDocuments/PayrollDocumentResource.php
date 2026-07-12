<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollDocuments;

use App\Filament\Resources\PayrollDocuments\Pages\CreatePayrollDocument;
use App\Filament\Resources\PayrollDocuments\Pages\EditPayrollDocument;
use App\Filament\Resources\PayrollDocuments\Pages\ListPayrollDocuments;
use App\Filament\Resources\PayrollDocuments\Pages\ReviewPayrollDocument;
use App\Filament\Resources\PayrollDocuments\Schemas\PayrollDocumentForm;
use App\Filament\Resources\PayrollDocuments\Tables\PayrollDocumentsTable;
use App\Models\PayrollDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollDocumentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'payroll_document';
    }

    protected static ?string $model = PayrollDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'document_number';

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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'remarks',
            'status',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PayrollDocument $record */
        return [
            'Period' => $record->period_start?->format('Y-m-d').' to '.$record->period_end?->format('Y-m-d'),
            'Status' => $record->status?->value ?? '—',
            'Net Pay' => number_format((float) $record->total_net_pay, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('period');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollDocuments::route('/'),
            'create' => CreatePayrollDocument::route('/create'),
            'edit' => EditPayrollDocument::route('/{record}/edit'),
            'review' => ReviewPayrollDocument::route('/{record}/review'),
        ];
    }
}
