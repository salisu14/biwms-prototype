<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FlushingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterBin extends Model
{
    use HasFactory;

    protected $table = 'work_center_bins';

    protected $fillable = [
        'work_center_id',
        'open_shop_floor_bin_id',
        'to_production_bin_id',
        'from_production_bin_id',
        'fixed_bin_id',
        'flushing_method',
    ];

    protected $casts = [
        'flushing_method' => FlushingMethod::class,
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function openShopFloorBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'open_shop_floor_bin_id');
    }

    public function toProductionBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'to_production_bin_id');
    }

    public function fromProductionBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_production_bin_id');
    }

    public function fixedBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'fixed_bin_id');
    }

    public function requiresPickDocument(): bool
    {
        return $this->flushing_method->requiresPickDocument();
    }
}
