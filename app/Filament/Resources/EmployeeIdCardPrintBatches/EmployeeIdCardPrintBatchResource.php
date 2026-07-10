<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardPrintBatches;

use App\Filament\Resources\EmployeeIdCardPrintBatches\Pages\ListEmployeeIdCardPrintBatches;
use App\Filament\Resources\EmployeeIdCardPrintBatches\Pages\ViewEmployeeIdCardPrintBatch;
use App\Models\EmployeeIdCardPrintBatch;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeIdCardPrintBatchResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_id_card_print_batch';
    }

    protected static ?string $model = EmployeeIdCardPrintBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Identity';

    protected static ?string $navigationLabel = 'Card Printing';

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch')->columns(3)->schema([
                TextEntry::make('batch_number'),
                TextEntry::make('layout')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('items_count')->counts('items')->label('Cards'),
                TextEntry::make('printedBy.name')->label('Printed By'),
                TextEntry::make('printed_at')->dateTime(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable(),
                TextColumn::make('layout')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('items_count')->counts('items')->label('Cards'),
                TextColumn::make('creator.name')->label('Created By')->toggleable(),
                TextColumn::make('printed_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeIdCardPrintBatches::route('/'),
            'view' => ViewEmployeeIdCardPrintBatch::route('/{record}'),
        ];
    }
}
