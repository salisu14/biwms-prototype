<?php

namespace App\Filament\Resources\PayCodes;

use App\Filament\Resources\PayCodes\Pages\CreatePayCode;
use App\Filament\Resources\PayCodes\Pages\EditPayCode;
use App\Filament\Resources\PayCodes\Pages\ListPayCodes;
use App\Filament\Resources\PayCodes\Schemas\PayCodeForm;
use App\Filament\Resources\PayCodes\Tables\PayCodesTable;
use App\Models\PayCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayCodeResource extends Resource
{
    protected static ?string $model = PayCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PayCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayCodesTable::configure($table);
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
            'index' => ListPayCodes::route('/'),
            'create' => CreatePayCode::route('/create'),
            'edit' => EditPayCode::route('/{record}/edit'),
        ];
    }
}
