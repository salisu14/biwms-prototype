<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GlEntry;
use App\Services\Accounting\GlAccountBalanceService;

class GlEntryObserver
{
    public function __construct(
        private readonly GlAccountBalanceService $balanceService,
    ) {}

    /**
     * Handle the GlEntry "created" event.
     */
    public function created(GlEntry $entry): void
    {
        $this->balanceService->syncAccount($entry->chart_of_account_id);
    }

    /**
     * Handle the GlEntry "updated" event.
     */
    public function updated(GlEntry $entry): void
    {
        if ($entry->wasChanged('chart_of_account_id')) {
            $this->balanceService->syncAccount($entry->getOriginal('chart_of_account_id'));
        }

        $this->balanceService->syncAccount($entry->chart_of_account_id);
    }

    /**
     * Handle the GlEntry "deleted" event.
     */
    public function deleted(GlEntry $entry): void
    {
        $this->balanceService->syncAccount($entry->chart_of_account_id);
    }

    /**
     * Handle the GlEntry "restored" event.
     */
    public function restored(GlEntry $entry): void
    {
        $this->balanceService->syncAccount($entry->chart_of_account_id);
    }

    /**
     * Handle the GlEntry "force deleted" event.
     */
    public function forceDeleted(GlEntry $entry): void
    {
        $this->balanceService->syncAccount($entry->chart_of_account_id);
    }
}
