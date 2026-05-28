<?php

namespace App\Filament\Pages\Finance;

use App\Models\FAJournalBatch;
use App\Models\FAJournalTemplate;
use App\Models\GeneralJournalBatch;
use App\Models\GeneralJournalTemplate;
use App\Models\ItemJournalBatch;
use App\Models\ItemJournalTemplate;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalTemplate;
use App\Models\RecurringJournalBatch;
use App\Models\RecurringJournalTemplate;
use App\Models\WarehouseJournalBatch;
use App\Models\WarehouseJournalTemplate;
use Filament\Pages\Page;

class GeneralJournals extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-book-open';

    protected string $view = 'filament.pages.finance.general-journals';

    protected static ?string $navigationLabel = 'Journals';

    protected static ?string $title = 'Journals Hub';

    protected static ?string $slug = 'journals';

    protected static bool $shouldRegisterNavigation = false;

    public function getViewData(): array
    {
        return [
            'counts' => [
                'gen_journal_templates' => GeneralJournalTemplate::count(),
                'gen_journal_batches' => GeneralJournalBatch::count(),
                'gen_open_batches' => GeneralJournalBatch::where('status', 'open')->count(),
                'item_journal_templates' => ItemJournalTemplate::count(),
                'item_journal_batches' => ItemJournalBatch::count(),
                'fa_journal_templates' => FAJournalTemplate::count(),
                'fa_journal_batches' => FAJournalBatch::count(),
                'prod_journal_batches' => ProductionJournalBatch::count(),
                'prod_journal_templates' => ProductionJournalTemplate::count(),
                'recurring_journal_batches' => RecurringJournalBatch::count(),
                'recurring_journal_templates' => RecurringJournalTemplate::count(),
                'warehouse_journal_batches' => WarehouseJournalBatch::count(),
                'warehouse_journal_templates' => WarehouseJournalTemplate::count(),
            ],
        ];
    }
}
