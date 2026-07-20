<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentOffers\Tables;

use App\Models\RecruitmentOffer;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentOffersTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentOffer::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
