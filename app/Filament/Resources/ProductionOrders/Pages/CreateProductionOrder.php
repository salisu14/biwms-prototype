<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Exceptions\MissingNumberSeriesException;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Manufacturing\ProductionOrder;
use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\NumberSeriesService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateProductionOrder extends CreateRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function afterFill(): void
    {
        try {
            app(NumberSeriesService::class)->previewNextNo(ProductionOrderNumberSeriesSetupService::CODE);
        } catch (MissingNumberSeriesException) {
            $this->sendMissingNumberSeriesNotification(warning: true);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $data['document_number'] = blank($data['document_number'] ?? null)
                ? app(ProductionOrderService::class)->generateDocumentNumber()
                : $data['document_number'];

            $record = ProductionOrder::withoutAutomaticDocumentNumbering(function () use ($data): ProductionOrder {
                /** @var ProductionOrder $record */
                $record = new ($this->getModel())($data);
                $record->forceFill(['document_number' => $data['document_number']]);
                $record->save();

                return $record;
            });

            return $record;
        } catch (MissingNumberSeriesException) {
            $this->sendMissingNumberSeriesNotification(warning: false);

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    private function sendMissingNumberSeriesNotification(bool $warning): void
    {
        $notification = Notification::make()
            ->title('Production Order Number Series is not configured')
            ->body('Production Order Number Series is not configured for the current business. Please contact the ERP administrator.')
            ->persistent();

        if ($warning) {
            $notification->warning();
        } else {
            $notification->danger();
        }

        $notification->send();
    }
}
