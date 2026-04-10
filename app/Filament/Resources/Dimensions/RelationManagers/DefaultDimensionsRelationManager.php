<?php

namespace App\Filament\Resources\Dimensions\RelationManagers;

use App\Enums\ValuePosting;
use App\Filament\Resources\Dimensions\DimensionResource;
use App\Models\DimensionValue;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DefaultDimensionsRelationManager extends RelationManager
{
    protected static string $relationship = 'defaultDimensions';

    protected static ?string $relatedResource = DimensionResource::class;

    protected static ?string $title = 'Default Dimensions';
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('dimension_code')
                    ->label('Dimension')
                    ->relationship('dimension', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Select::make('dimension_value_code')
                    ->label('Default Value')
                    ->options(function (Get $get) {
                        $dimCode = $get('dimension_code');
                        if (!$dimCode) return [];

                        return DimensionValue::whereHas('dimension', fn($q) => $q->where('code', $dimCode))
                            ->pluck('name', 'code');
                    })
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get) => !$get('dimension_code')),

                Select::make('value_posting')
                    ->label('Posting Rule')
                    ->options(ValuePosting::class)
                    ->required()
                    ->helperText('Defines if this dimension is mandatory or restricted.'),

                Toggle::make('blocked')
                    ->label('Rule Inactive'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dimension.name')
                    ->label('Dimension')
                    ->sortable(),
                TextColumn::make('dimensionValue.name')
                    ->label('Default Value')
                    ->placeholder('No Default'),
                TextColumn::make('value_posting')
                    ->label('Rule')
                    ->badge(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('value_posting')
                    ->options(ValuePosting::class),
            ])
            ->headerActions([
                CreateAction::make()
                    // Logic to handle the custom table_id/no link
                    ->mutateDataUsing(function (array $data): array {
                        $data['table_id'] = $this->getOwnerRecord()->getTable();
                        $data['no'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
