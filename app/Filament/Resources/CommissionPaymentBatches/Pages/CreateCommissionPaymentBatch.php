<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentBatches\Pages;

use App\Filament\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;
use App\Models\CommissionPaymentBatch;
use App\Models\CommissionSettlementBatch;
use App\Services\Sales\ReferralCommissions\CommissionPaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommissionPaymentBatch extends CreateRecord
{
    protected static string $resource = CommissionPaymentBatchResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CommissionPaymentService::class)->createBatchFromSettlement(
            CommissionSettlementBatch::query()->findOrFail($data['commission_settlement_batch_id']),
            $data,
            auth()->user(),
        );
    }

    protected function getRedirectUrl(): string
    {
        /** @var CommissionPaymentBatch $record */
        $record = $this->record;

        return static::getResource()::getUrl('view', ['record' => $record]);
    }
}
