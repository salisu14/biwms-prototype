<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Exceptions\MissingNumberSeriesException;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Traits\ShowsMissingNumberSeriesWarning;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    use ShowsMissingNumberSeriesWarning;

    protected static string $resource = PaymentResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->warnIfMissingNumberSeries(['PAYMENT'], 'Payment');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (MissingNumberSeriesException) {
            Notification::make()
                ->title('Payment Number Series is not configured')
                ->body('Payment Number Series PAYMENT is not configured for the current business. Please contact the ERP administrator.')
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
