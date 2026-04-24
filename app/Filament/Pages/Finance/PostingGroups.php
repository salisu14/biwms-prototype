<?php

namespace App\Filament\Pages\Finance;

use App\Models\CustomerPostingGroup;
use App\Models\EmployeePostingGroup;
use App\Models\FAPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\PayrollPostingGroup;
use App\Models\VatBusinessPostingGroup;
use App\Models\VatPostingSetup;
use App\Models\VatProductPostingGroup;
use Filament\Pages\Page;

class PostingGroups extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.pages.finance.posting-groups';

    protected static ?string $navigationLabel = 'Posting Groups';

    protected static ?string $title = 'Posting Groups Hub';

    protected static string|null|\UnitEnum $navigationGroup = 'Accounting';

    protected static bool $shouldRegisterNavigation = false;

    public function getViewData(): array
    {
        return [
            'counts' => [
                'gen_posting_setup' => GeneralPostingSetup::count(),
                'gen_bus_posting_group' => GeneralBusinessPostingGroup::count(),
                'gen_prod_posting_group' => GeneralProductPostingGroup::count(),
                'vat_posting_setup' => VatPostingSetup::count(),
                'vat_bus_posting_group' => VatBusinessPostingGroup::count(),
                'vat_prod_posting_group' => VatProductPostingGroup::count(),
                'customer_posting_group' => CustomerPostingGroup::count(),
                'employee_posting_group' => EmployeePostingGroup::count(),
                'payroll_posting_group' => PayrollPostingGroup::count(),
                'inventory_posting_setup' => InventoryPostingSetup::count(),
                'inventory_posting_group' => InventoryPostingGroup::count(),
                'fa_posting_group' => FAPostingGroup::count(),
            ],
        ];
    }
}
