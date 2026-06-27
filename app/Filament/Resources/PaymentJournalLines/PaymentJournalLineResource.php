<?php

namespace App\Filament\Resources\PaymentJournalLines;

use App\Filament\Resources\PaymentJournalLines\Pages\CreatePaymentJournalLine;
use App\Filament\Resources\PaymentJournalLines\Pages\EditPaymentJournalLine;
use App\Filament\Resources\PaymentJournalLines\Pages\ListPaymentJournalLines;
use App\Filament\Resources\PaymentJournalLines\Pages\ViewPaymentJournalLine;
use App\Filament\Resources\PaymentJournalLines\Schemas\PaymentJournalLineForm;
use App\Filament\Resources\PaymentJournalLines\Schemas\PaymentJournalLineInfolist;
use App\Filament\Resources\PaymentJournalLines\Tables\PaymentJournalLinesTable;
use App\Models\PaymentJournalLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentJournalLineResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'payment_journal_line';
    }

    protected static ?string $model = PaymentJournalLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 24;

    protected static ?string $navigationLabel = 'Payment Journal';

    protected static ?string $recordTitleAttribute = 'vendor_no';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return PaymentJournalLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentJournalLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentJournalLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentJournalLines::route('/'),
            'create' => CreatePaymentJournalLine::route('/create'),
            'view' => ViewPaymentJournalLine::route('/{record}'),
            'edit' => EditPaymentJournalLine::route('/{record}/edit'),
        ];
    }
}
