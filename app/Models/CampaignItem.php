<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'item_id',
        'special_price',
        'discount_percent',
    ];

    protected $casts = [
        'special_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
