<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentApplications;

use App\Enums\CommissionPaymentApplicationType;
use App\Filament\Resources\CommissionPaymentApplications\Pages\ListCommissionPaymentApplications;
use App\Filament\Resources\CommissionPaymentApplications\Pages\ViewCommissionPaymentApplication;
use App\Models\CommissionPaymentApplication;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionPaymentApplicationResource extends Resource
{
    protected static ?string $model = CommissionPaymentApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Applications';

    protected static ?int $navigationSort = 86;

    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_payment_application';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('batch.batch_number')->label('Batch')->searchable(),
            TextColumn::make('line.referrer.name')->label('Referrer')->searchable(),
            TextColumn::make('application_type')->badge()->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('currency_code')->badge(),
            TextColumn::make('applied_amount')->money(fn (CommissionPaymentApplication $record): string => $record->currency_code)->sortable(),
            TextColumn::make('posting_date')->date()->sortable(),
        ])->filters([
            SelectFilter::make('application_type')->options(CommissionPaymentApplicationType::class),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionPaymentApplications::route('/'),
            'view' => ViewCommissionPaymentApplication::route('/{record}'),
        ];
    }
}
