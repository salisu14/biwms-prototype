<?php

namespace App\Observers;

use App\Models\GlEntry;
use App\Models\ChartOfAccount;

class GlEntryObserver
{
    /**
     * Handle the GlEntry "created" event.
     */
    public function created(GlEntry $entry): void
    {
        $this->updateAccountBalance($entry->chart_of_account_id, $entry->amount);
    }

    /**
     * Handle the GlEntry "updated" event.
     */
    public function updated(GlEntry $entry): void
    {
        $delta = $entry->amount - $entry->getOriginal('amount');
        if ($delta != 0) {
            $this->updateAccountBalance($entry->chart_of_account_id, $delta);
        }
        
        // Handle account change
        if ($entry->wasChanged('chart_of_account_id')) {
            // Subtract from old account
            $oldAccountId = $entry->getOriginal('chart_of_account_id');
            $this->updateAccountBalance($oldAccountId, -$entry->getOriginal('amount'));
            
            // Add back to new account (already handled by delta loop? No, handled manually)
            $this->updateAccountBalance($entry->chart_of_account_id, $entry->amount);
        }
    }

    /**
     * Handle the GlEntry "deleted" event.
     */
    public function deleted(GlEntry $entry): void
    {
        $this->updateAccountBalance($entry->chart_of_account_id, -$entry->amount);
    }

    /**
     * Update the balance of a ChartOfAccount.
     */
    protected function updateAccountBalance($accountId, float $amount): void
    {
        if (!$accountId) return;

        $account = ChartOfAccount::find($accountId);
        if ($account) {
            $account->increment('balance', $amount);
        }
    }
}
