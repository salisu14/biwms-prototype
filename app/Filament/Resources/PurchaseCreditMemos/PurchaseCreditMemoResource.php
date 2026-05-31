<?php

namespace App\Filament\Resources\PurchaseCreditMemos;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseCreditMemos\Pages\CreatePurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Pages\EditPurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Pages\ListPurchaseCreditMemos;
use App\Filament\Resources\PurchaseCreditMemos\Pages\ViewPurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Schemas\PurchaseCreditMemoForm;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Models\PurchaseCreditMemo;
use App\Services\Purchases\PurchaseCreditMemoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PurchaseCreditMemoResource extends Resource
{
    protected static ?string $model = PurchaseCreditMemo::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string|UnitEnum|null $navigationGroup = 'Purchases';

    public static function form(Schema $schema): Schema
    {
        return PurchaseCreditMemoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')->searchable()->sortable(),
                TextColumn::make('vendor_name')->searchable(),
                TextColumn::make('grand_total')->money('NGN')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->icon(fn ($state) => $state->icon()),
                TextColumn::make('posting_date')->date(),
            ])
            ->recordActions([
                ApprovalActions::makeSendApprovalRequestAction(),
                ApprovalActions::makeCancelApprovalRequestAction(),
                ApprovalActions::makeApproveAction(),
                ApprovalActions::makeRejectAction(),
                ApprovalActions::makeDelegateAction(),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === ApprovalStatus::APPROVED && ! $record->isPendingApproval())
                    ->action(function ($record) {
                        app(PurchaseCreditMemoService::class)->post($record);
                        Notification::make()->title('Credit memo posted successfully')->success()->send();
                    }),
            ]);
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
            'index' => ListPurchaseCreditMemos::route('/'),
            'create' => CreatePurchaseCreditMemo::route('/create'),
            'view' => ViewPurchaseCreditMemo::route('/{record}'),
            'edit' => EditPurchaseCreditMemo::route('/{record}/edit'),
        ];
    }
}
