<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'main_asset_id',
        'component_asset_id',
        'quantity',
        'notes',
    ];

    public function mainAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'main_asset_id');
    }

    public function componentAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'component_asset_id');
    }
}
