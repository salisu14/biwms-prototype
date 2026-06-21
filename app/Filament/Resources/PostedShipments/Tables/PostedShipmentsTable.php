<?php

namespace App\Filament\Resources\PostedShipments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class PostedShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
