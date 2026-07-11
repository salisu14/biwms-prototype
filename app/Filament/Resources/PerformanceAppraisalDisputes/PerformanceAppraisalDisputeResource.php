<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalDisputes;

use App\Filament\Resources\PerformanceAppraisalDisputes\Pages\CreatePerformanceAppraisalDispute;
use App\Filament\Resources\PerformanceAppraisalDisputes\Pages\EditPerformanceAppraisalDispute;
use App\Filament\Resources\PerformanceAppraisalDisputes\Pages\ListPerformanceAppraisalDisputes;
use App\Filament\Resources\PerformanceAppraisalDisputes\Pages\ViewPerformanceAppraisalDispute;
use App\Filament\Resources\PerformanceAppraisalDisputes\Schemas\PerformanceAppraisalDisputeForm;
use App\Filament\Resources\PerformanceAppraisalDisputes\Schemas\PerformanceAppraisalDisputeInfolist;
use App\Filament\Resources\PerformanceAppraisalDisputes\Tables\PerformanceAppraisalDisputesTable;
use App\Models\PerformanceAppraisalDispute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalDisputeResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_appraisal_dispute';
    }

    protected static ?string $model = PerformanceAppraisalDispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalDisputeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalDisputeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalDisputesTable::configure($table);
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
            'index' => ListPerformanceAppraisalDisputes::route('/'),
            'create' => CreatePerformanceAppraisalDispute::route('/create'),
            'view' => ViewPerformanceAppraisalDispute::route('/{record}'),
            'edit' => EditPerformanceAppraisalDispute::route('/{record}/edit'),
        ];
    }
}
