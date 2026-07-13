<?php

namespace App\Filament\Resources\PerformanceAppraisalCycles\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AppraisalsRelationManager extends RelationManager
{
    protected static string $relationship = 'appraisals';

    protected static ?string $title = 'Appraisals';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee.full_name')
            ->columns([
                Tables\Columns\TextColumn::make('assignment.employee.full_name')
                    ->searchable()
                    ->sortable()
                    ->label('Employee')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('assignment.manager.full_name')
                    ->searchable()
                    ->sortable()
                    ->label('Reviewer'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => ['in_progress', 'pending_review'],
                        'info' => 'under_review',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('overall_rating')
                    ->label('Overall Rating')
                    ->placeholder('Pending')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'submitted' => 'Submitted',
                        'under_review' => 'Under Review',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                // No create action - appraisals are generated from assignments
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('generateAppraisal')
                    ->label('Generate Appraisal')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // Logic to generate appraisal from assignment template
                        $record->generateFromTemplate();
                    })
                    ->visible(fn ($record): bool => $record->status === 'draft'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportResults')
                        ->label('Export Results')
                        ->icon('heroicon-o-arrow-down-on-square-stack')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            // Export logic here
                        }),
                ]),
            ])
            ->emptyStateHeading('No appraisals generated yet')
            ->emptyStateDescription('Appraisals will appear here once they are created from assignments.')
            ->defaultSort('created_at', 'desc');
    }
}
