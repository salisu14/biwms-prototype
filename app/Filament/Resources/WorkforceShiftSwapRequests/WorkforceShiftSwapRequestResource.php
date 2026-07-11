<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests;

use App\Filament\Resources\WorkforceShiftSwapRequests\Pages\CreateWorkforceShiftSwapRequest;
use App\Filament\Resources\WorkforceShiftSwapRequests\Pages\EditWorkforceShiftSwapRequest;
use App\Filament\Resources\WorkforceShiftSwapRequests\Pages\ListWorkforceShiftSwapRequests;
use App\Filament\Resources\WorkforceShiftSwapRequests\Pages\ViewWorkforceShiftSwapRequest;
use App\Filament\Resources\WorkforceShiftSwapRequests\Schemas\WorkforceShiftSwapRequestForm;
use App\Filament\Resources\WorkforceShiftSwapRequests\Schemas\WorkforceShiftSwapRequestInfolist;
use App\Filament\Resources\WorkforceShiftSwapRequests\Tables\WorkforceShiftSwapRequestsTable;
use App\Models\WorkforceShiftSwapRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceShiftSwapRequestResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'shift_swap_request';
    }

    protected static ?string $model = WorkforceShiftSwapRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Shift Swap Requests';

    public static function form(Schema $schema): Schema
    {
        return WorkforceShiftSwapRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceShiftSwapRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceShiftSwapRequestsTable::configure($table);
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
            'index' => ListWorkforceShiftSwapRequests::route('/'),
            'create' => CreateWorkforceShiftSwapRequest::route('/create'),
            'view' => ViewWorkforceShiftSwapRequest::route('/{record}'),
            'edit' => EditWorkforceShiftSwapRequest::route('/{record}/edit'),
        ];
    }
}
