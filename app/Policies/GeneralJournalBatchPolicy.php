<?php

namespace App\Policies;

class GeneralJournalBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.general_journal_batch';
    }

    protected function legacyKey(): string
    {
        return 'general_journal_batch';
    }
}
