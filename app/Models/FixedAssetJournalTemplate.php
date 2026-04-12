<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAssetJournalTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'number_series_id',
        'source_code',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(FixedAssetJournalBatch::class, 'template_id');
    }
}
