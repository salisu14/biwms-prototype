<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Tables;

use App\Models\EmployeeConfirmationDecision;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class EmployeeConfirmationDecisionsTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, EmployeeConfirmationDecision::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
