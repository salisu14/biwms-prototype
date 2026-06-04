<?php

namespace App\Filament\Resources\SocialSecurityTiers\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Resources\SocialSecurityTiers\SocialSecurityTierResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSocialSecurityTier extends ViewRecord
{
    protected static string $resource = SocialSecurityTierResource::class;

    public function getHeading(): string
    {
        return 'Social Security Tier Code '.$this->record->tier_code;
    }

    public function getSubheading(): string
    {
        return 'Code '.($this->record->tier_code ?? '—')
            .' • Scope '.(($this->record->from_salary ?? '—').' - '.($this->record->to_salary ?? 'Unlimited'))
            .' • Attribute '.(($this->record->employee_rate ?? '—').'% / '.($this->record->employer_rate ?? '—').'%');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPayrollDocuments')
                ->label('Open Payroll Documents')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(PayrollDocumentResource::getUrl()),
            EditAction::make(),
        ];
    }
}
