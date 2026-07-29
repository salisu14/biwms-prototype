<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionDowntimeCategory;
use Illuminate\Database\Eloquent\Model;

class ProductionDowntimeReason extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'requires_approval',
        'blocks_completion',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'category' => ProductionDowntimeCategory::class,
        'requires_approval' => 'boolean',
        'blocks_completion' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
