<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\Referrers\Pages;

use App\Exceptions\MissingNumberSeriesException;
use App\Filament\Sales\Resources\Referrers\ReferrerResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateReferrer extends CreateRecord
{
    protected static string $resource = ReferrerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (MissingNumberSeriesException) {
            Notification::make()
                ->title('Referrer Number Series is not configured')
                ->body('Referrer Number Series is not configured. Please contact the ERP administrator.')
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
