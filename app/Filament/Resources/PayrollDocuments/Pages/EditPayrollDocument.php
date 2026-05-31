<?php

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Traits\ShowsMissingApprovalTemplateWarning;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayrollDocument extends EditRecord
{
    use ShowsMissingApprovalTemplateWarning;

    protected static string $resource = PayrollDocumentResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->warnIfMissingApprovalTemplate($this->record, 'Payroll Document');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')
                ->label('Review')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn (): string => PayrollDocumentResource::getUrl('review', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
