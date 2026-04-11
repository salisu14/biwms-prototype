<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseCategory extends ViewRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('budgetAnalysis')
                ->label('Budget Analysis')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action(function (ExpenseCategory $record, ExpenseService $service) {
                    $variance = $service->getCategoryBudgetVariance($record, (int) now()->year, (int) now()->month);

                    Notification::make()
                        ->title('Budget Status for '.now()->format('F Y'))
                        ->body('Actual: '.number_format($variance['actual'], 2).' / Budgeted: '.number_format($variance['budgeted'], 2))
                        ->success()
                        ->send();
                }),
        ];
    }
}
