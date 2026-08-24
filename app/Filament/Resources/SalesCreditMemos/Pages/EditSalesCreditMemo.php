<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Data\Sales\SalesCreditMemoData;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Models\SalesCreditMemo;
use App\Services\Sales\SalesCreditMemoService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSalesCreditMemo extends EditRecord
{
    protected static string $resource = SalesCreditMemoResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof SalesCreditMemo && $this->record->isPosted()) {
            abort(403);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->authorize(fn (SalesCreditMemo $record): bool => auth()->user()?->can('delete', $record) === true)
                ->hidden(fn (SalesCreditMemo $record): bool => $record->isPosted()),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var SalesCreditMemo $record */
        return app(SalesCreditMemoService::class)->update($record, SalesCreditMemoData::from($data));
    }
}
