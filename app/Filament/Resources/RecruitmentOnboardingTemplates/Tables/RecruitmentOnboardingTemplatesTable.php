<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOnboardingTemplates\Tables;

use App\Models\RecruitmentOnboardingTemplate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentOnboardingTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return CompletedResourceSchema::table($table, RecruitmentOnboardingTemplate::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
