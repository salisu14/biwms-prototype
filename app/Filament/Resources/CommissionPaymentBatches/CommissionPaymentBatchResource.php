<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentBatches;

use App\Enums\CommissionPaymentBatchStatus;
use App\Enums\CommissionPaymentMethod;
use App\Enums\CommissionSettlementBatchStatus;
use App\Filament\Resources\CommissionPaymentBatches\Pages\CreateCommissionPaymentBatch;
use App\Filament\Resources\CommissionPaymentBatches\Pages\ListCommissionPaymentBatches;
use App\Filament\Resources\CommissionPaymentBatches\Pages\ViewCommissionPaymentBatch;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionSettlementBatch;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionPaymentBatchResource extends Resource
{
    protected static ?string $model = CommissionPaymentBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports & Analysis';

    protected static ?string $navigationLabel = 'Commission Payments';

    protected static ?int $navigationSort = 83;

    protected static bool $isGloballySearchable = false;

    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'commission_payment_batch';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Batch')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('commission_settlement_batch_id')
                        ->label('Locked Settlement Batch')
                        ->options(fn (): array => CommissionSettlementBatch::query()
                            ->where('status', CommissionSettlementBatchStatus::Locked)
                            ->orderByDesc('id')
                            ->pluck('settlement_number', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('payment_method')
                        ->options(collect([CommissionPaymentMethod::BankTransfer, CommissionPaymentMethod::Cash, CommissionPaymentMethod::Cheque])->mapWithKeys(fn (CommissionPaymentMethod $method): array => [$method->value => str($method->value)->replace('_', ' ')->headline()->toString()])->all())
                        ->default(CommissionPaymentMethod::BankTransfer->value)
                        ->required()
                        ->live(),
                    Select::make('bank_account_id')
                        ->relationship('bankAccount', 'account_name')
                        ->searchable()
                        ->visible(fn ($get): bool => in_array($get('payment_method'), [CommissionPaymentMethod::BankTransfer->value, CommissionPaymentMethod::Cheque->value], true)),
                    Select::make('cash_account_id')
                        ->relationship('cashAccount', 'name')
                        ->searchable()
                        ->visible(fn ($get): bool => $get('payment_method') === CommissionPaymentMethod::Cash->value),
                    DatePicker::make('payment_date')->default(now())->required(),
                    DatePicker::make('posting_date')->default(now())->required(),
                    TextInput::make('external_reference')->maxLength(255),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable(),
                TextColumn::make('settlementBatch.settlement_number')->label('Settlement')->searchable(),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('currency_code')->badge(),
                TextColumn::make('total_amount')->money(fn (CommissionPaymentBatch $record): string => $record->currency_code)->sortable(),
                TextColumn::make('line_count')->numeric()->sortable(),
                TextColumn::make('referrer_count')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('posted_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(CommissionPaymentBatchStatus::class),
                SelectFilter::make('payment_method')->options(CommissionPaymentMethod::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionPaymentBatches::route('/'),
            'create' => CreateCommissionPaymentBatch::route('/create'),
            'view' => ViewCommissionPaymentBatch::route('/{record}'),
        ];
    }
}
