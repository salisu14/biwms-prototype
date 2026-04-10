<?php

namespace App\Filament\Resources\Contacts\Tables;

use App\Enums\ContactRole;
use App\Enums\CustomerType;
use App\Models\Contact;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\VendorPostingGroup;
use App\Services\ContactService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('bold')->searchable()->sortable(),
                TextColumn::make('company_name')->searchable()->toggleable(),
                TextColumn::make('email')->icon('heroicon-m-envelope')->searchable(),
                TextColumn::make('phone')->searchable()->toggleable(),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('city')->toggleable(),
                TextColumn::make('country')->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('convertToCustomer')
                    ->label('Convert to Customer')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (Contact $record) => ! in_array($record->role, [ContactRole::CUSTOMER, ContactRole::BOTH]))
                    ->form([
                        Select::make('customer_type')
                            ->options(CustomerType::class)
                            ->required()
                            ->label('Customer Type'),
                        Select::make('customer_posting_group_id')
                            ->label('Customer Posting Group')
                            ->options(CustomerPostingGroup::all()->pluck('code', 'id'))
                            ->required(),
                        Select::make('general_business_posting_group_id')
                            ->label('General Business Posting Group')
                            ->options(GeneralBusinessPostingGroup::all()->pluck('code', 'id'))
                            ->required(),
                    ])
                    ->action(function (Contact $record, array $data) {
                        try {
                            app(ContactService::class)->convertToCustomer($record, $data);
                            Notification::make()->title('Contact converted to Customer successfully')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Conversion failed: '.$e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('convertToVendor')
                    ->label('Convert to Vendor')
                    ->icon('heroicon-o-building-office-2')
                    ->color('info')
                    ->visible(fn (Contact $record) => ! in_array($record->role, [ContactRole::VENDOR, ContactRole::BOTH]))
                    ->form([
                        Select::make('vendor_posting_group_id')
                            ->label('Vendor Posting Group')
                            ->options(VendorPostingGroup::all()->pluck('code', 'id'))
                            ->required(),
                        Select::make('general_business_posting_group_id')
                            ->label('General Business Posting Group')
                            ->options(GeneralBusinessPostingGroup::all()->pluck('code', 'id'))
                            ->required(),
                    ])
                    ->action(function (Contact $record, array $data) {
                        try {
                            app(ContactService::class)->convertToVendor($record, $data);
                            Notification::make()->title('Contact converted to Vendor successfully')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Conversion failed: '.$e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
