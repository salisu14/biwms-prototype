<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CustomerOnAccountWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Customer On Account (Unapplied Receipts)';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->selectRaw('max(id) as id, party_id, max(party_name) as party_name, currency_code, count(*) as open_receipts_count, sum(unapplied_amount) as on_account_amount')
                    ->where('party_type', 'CUSTOMER')
                    ->where('status', 'POSTED')
                    ->where('unapplied_amount', '>', 0.01)
                    ->groupBy('party_id', 'currency_code')
                    ->orderByDesc('on_account_amount')
                    ->limit(15)
            )
            ->defaultKeySort(false)
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('party_name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('open_receipts_count')
                    ->label('Open Receipts')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('on_account_amount')
                    ->label('On Account')
                    ->money(fn ($record) => $record->currency_code ?: 'NGN')
                    ->alignment('right'),
                Tables\Columns\TextColumn::make('currency_code')
                    ->label('Currency')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('view_payments')
                    ->label('View Payments')
                    ->icon('heroicon-m-eye')
                    ->url(fn ($record): string => route('filament.admin.resources.payments.index', [
                        'tableSearch' => $record->party_name,
                    ])),
            ]);
    }
}
