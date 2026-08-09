<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCampaignOrder extends Model
{
    protected $fillable = [
        'production_campaign_id',
        'production_order_id',
        'sequence',
        'planned_quantity_base',
        'setup_class',
        'changeover_class',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_quantity_base' => 'decimal:8',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ProductionCampaign::class, 'production_campaign_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
