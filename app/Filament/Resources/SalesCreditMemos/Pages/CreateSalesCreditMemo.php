<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Data\Sales\SalesCreditMemoData;
use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Services\Sales\SalesCreditMemoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSalesCreditMemo extends CreateRecord
{
    protected static string $resource = SalesCreditMemoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from($data));
        } catch (BusinessException $exception) {
            Notification::make()
                ->title($exception->title())
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                $exception->field() => $exception->getMessage(),
            ]);
        }
    }
}
