<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseJournalTemplate extends Model
{
    //
    public function batches(): HasMany
    {
        return $this->hasMany(WarehouseJournalBatch::class, 'template_id');
    }
}
