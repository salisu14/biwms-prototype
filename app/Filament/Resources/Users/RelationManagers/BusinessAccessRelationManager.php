<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Business;
use App\Services\Business\BusinessEntitlementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessAccessRelationManager extends RelationManager
{
    protected static string $relationship = 'businesses';

    protected static ?string $title = 'Business Access';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Business')->searchable()->sortable(),
                TextColumn::make('pivot.created_at')->label('Granted')->dateTime()->placeholder('Legacy assignment'),
            ])
            ->headerActions([
                Action::make('grant_business_access')
                    ->label('Grant Business Access')
                    ->form([
                        Select::make('business_id')
                            ->label('Business')
                            ->options(fn (): array => Business::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        app(BusinessEntitlementService::class)->grant(
                            $this->getOwnerRecord(),
                            Business::query()->findOrFail($data['business_id']),
                            auth()->id(),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('revoke_business_access')
                    ->label('Revoke')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (Business $record): void {
                        app(BusinessEntitlementService::class)->revoke(
                            $this->getOwnerRecord(),
                            $record,
                            auth()->id(),
                        );
                    }),
            ]);
    }
}
