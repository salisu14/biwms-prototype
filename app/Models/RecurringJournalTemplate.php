<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringJournalTemplate extends Model
{
    //
    public function batches(): HasMany
    {
        return $this->hasMany(RecurringJournalBatch::class, 'template_id');
    }
}
