<?php

namespace App\Filament\Resources\FAJournalBatches\RelationManagers;

use App\Enums\FAPostingType;
use App\Models\FixedAsset;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('line_no')
                                ->label('Line No.')
                                ->numeric()
                                ->required()
                                ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10),

                            DatePicker::make('posting_date')
                                ->label('Posting Date')
                                ->required()
                                ->default(fn ($livewire) => $livewire->ownerRecord->posting_date ?? now())
                                ->native(false),

                            TextInput::make('document_no')
                                ->label('Document No.')
                                ->required()
                                ->maxLength(50),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('fixed_asset_id')
                                ->label('Fixed Asset')
                                ->relationship('fixedAsset', 'fa_no')
                                ->getOptionLabelFromRecordUsing(fn (FixedAsset $record) => "{$record->fa_no} – {$record->description}")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, $get) {
                                    if (!$state) return;
                                    $asset = FixedAsset::find($state);
                                    if ($asset) {
                                        // Only populate if the user hasn't manually typed a description
                                        if (empty($get('description'))) {
                                            $set('description', $asset->description);
                                        }
                                        $set('fa_no', $asset->fa_no);
                                        $set('fa_posting_group_id', $asset->fa_posting_group_id);

                                        // If asset has a depreciation book, suggest it (optional UX improvement)
                                        if ($asset->depreciationBook) {
                                            $set('depreciation_book_code', $asset->depreciationBook->code);
                                        }
                                    }
                                }),

                            Select::make('fa_posting_type')
                                ->label('Posting Type')
                                ->options(FAPostingType::class)
                                ->required()
                                ->native(false)
                                ->live(),

                            Select::make('depreciation_book_id')
                                ->label('Depreciation Book')
                                ->relationship('depreciationBook', 'code')
                                ->searchable()
                                ->preload(),

                            TextInput::make('description')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Financials')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->step(0.0001)
                                ->helperText('Value of the transaction (Acquisition, Depr, etc.)'),

                            TextInput::make('number_of_depreciation_days')
                                ->label('Depr. Days')
                                ->numeric()
                                ->placeholder('Optional')
                                ->visible(fn (callable $get) => $get('fa_posting_type') === FAPostingType::DEPRECIATION->value),

                            Toggle::make('calculate_depreciation')
                                ->label('Calc. Depr')
                                ->default(fn ($livewire) => $livewire->ownerRecord->calculate_depreciation)
                                ->inline(false),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fixedAsset.fa_no')
                    ->label('Asset No.')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('fa_posting_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        FAPostingType::ACQUISITION => 'success',
                        FAPostingType::DEPRECIATION => 'warning',
                        FAPostingType::DISPOSAL => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->alignment('right')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\IconColumn::make('line_status')
                    ->label('Posted')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Asset Transaction')
                    ->mutateDataUsing(function (array $data): array {
                        if (isset($data['fixed_asset_id'])) {
                            $asset = \App\Models\FixedAsset::find($data['fixed_asset_id']);
                            if ($asset) {
                                $data['fa_no'] = $asset->fa_no;
                                $data['fa_posting_group_id'] = $asset->fa_posting_group_id;

                                if (empty($data['description'])) {
                                    $data['description'] = $asset->description;
                                }
                            }
                        }
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->hidden(fn ($record) => $record->line_status === 'posted')
                    ->disabled(fn ($record): bool =>
                        // Logic derived from FAJournalLine::post() method
                        !filled($record->depreciation_book_code) && !filled($record->fixedAsset?->depreciationBook)
                    )
                    ->tooltip(fn ($record): ?string =>
                    ($record->line_status === 'posted')
                        ? 'This line has already been posted.'
                        : (blank($record->depreciation_book_code) && blank($record->fixedAsset?->depreciationBook)
                        ? 'Error: No Depreciation Book defined on Asset or Line.'
                        : null
                    )
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Post FA Journal Line?')
                    ->modalDescription('This will create ledger entries. This action cannot be undone.')
                    ->action(function ($record) {
                        try {
                            $record->post();

                            // Explicitly update status if the model's post() doesn't handle it internally
                            // (The provided code in the prompt manually updated it, so we keep that here)
                            $record->update(['line_status' => 'posted']);

                            Notification::make()
                                ->title('Line Posted')
                                ->body("Line {$record->line_no} posted successfully.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Posting Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('line_no', 'asc');
    }

    protected function mutateData(array $data): array
    {
        if (isset($data['fixed_asset_id'])) {
            $asset = FixedAsset::find($data['fixed_asset_id']);

            if ($asset) {
                $data['fa_no'] = $asset->fa_no;
                $data['fa_posting_group_id'] = $asset->fa_posting_group_id;

                if (empty($data['description'])) {
                    $data['description'] = $asset->description;
                }
            }
        }

        return $data;
    }
}
