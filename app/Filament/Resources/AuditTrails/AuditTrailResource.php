<?php

namespace App\Filament\Resources\AuditTrails;

use App\Filament\Resources\AuditTrails\Pages\ListAuditTrails;
use App\Filament\Resources\AuditTrails\Pages\ViewAuditTrail;
use App\Filament\Resources\AuditTrails\Schemas\AuditTrailInfolist;
use App\Filament\Resources\AuditTrails\Tables\AuditTrailsTable;
use App\Models\AuditTrail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuditTrailResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'admin';
    }

    public static function permissionResource(): string
    {
        return 'audit_trail';
    }

    protected static ?string $model = AuditTrail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Audit Trail';

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $recordTitleAttribute = 'description';

    public static function infolist(Schema $schema): Schema
    {
        return AuditTrailInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditTrailsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditTrails::route('/'),
            'view' => ViewAuditTrail::route('/{record}'),
        ];
    }
}
