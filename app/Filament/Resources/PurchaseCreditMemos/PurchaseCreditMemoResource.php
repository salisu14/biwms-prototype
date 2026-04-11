<?php

namespace App\Filament\Resources\PurchaseCreditMemos;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseCreditMemos\Pages\CreatePurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Pages\EditPurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Pages\ListPurchaseCreditMemos;
use App\Filament\Resources\PurchaseCreditMemos\Pages\ViewPurchaseCreditMemo;
use App\Filament\Resources\PurchaseCreditMemos\Schemas\PurchaseCreditMemoForm;
use App\Models\PurchaseCreditMemo;
use App\Services\Purchases\PurchaseCreditMemoService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action as TableAction;
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
                TextColumn::make('grand_total')->money('USD')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->icon(fn ($state) => $state->icon()),
                TextColumn::make('posting_date')->date(),
            ])
            ->actions([
                TableAction::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === ApprovalStatus::DRAFT)
                    ->action(function ($record) {
                        app(PurchaseCreditMemoService::class)->submitForApproval($record);
                        Notification::make()->title('Credit memo submitted for approval')->success()->send();
                    }),

                TableAction::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === ApprovalStatus::PENDING && auth()->user()->hasRole('super_admin'))
                    ->action(function ($record) {
                        app(PurchaseCreditMemoService::class)->approve($record, auth()->id());
                        Notification::make()->title('Credit memo approved')->success()->send();
                    }),

                TableAction::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === ApprovalStatus::PENDING && auth()->user()->hasRole('super_admin'))
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function ($record, array $data) {
                        app(PurchaseCreditMemoService::class)->reject($record, auth()->id(), $data['reason']);
                        Notification::make()->title('Credit memo rejected')->danger()->send();
                    }),

                TableAction::make('post')
                    ->label('Post')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === ApprovalStatus::APPROVED)
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
