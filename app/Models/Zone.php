<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WarehouseClass;
use App\Enums\ZoneType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'zone_code',
        'zone_name',
        'description',
        'zone_type',
        'warehouse_class',
        'bin_type_code',
        'bin_mandatory',
        'max_weight',
        'blocked',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'zone_type' => ZoneType::class,
        'warehouse_class' => WarehouseClass::class,
        'bin_mandatory' => 'boolean',
        'blocked' => 'boolean',
        'is_active' => 'boolean',
        'max_weight' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class, 'zone_id');
    }

    public function isProductionZone(): bool
    {
        return in_array($this->zone_type, [ZoneType::RECEIVING, 'production_output']); // Example logic
    }
}
