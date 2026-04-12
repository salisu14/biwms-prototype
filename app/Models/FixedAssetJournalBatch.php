<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAssetJournalBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'status',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FixedAssetJournalTemplate::class, 'template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FixedAssetJournalLine::class, 'batch_id');
    }
}
