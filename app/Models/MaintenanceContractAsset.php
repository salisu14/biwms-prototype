<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceContractAsset extends Model
{
    use HasFactory;

    protected $table = 'maintenance_contract_assets';

    protected $fillable = [
        'maintenance_contract_id',
        'fixed_asset_id',
        'covered_serial_no',
        'special_conditions',
        'asset_specific_limit',
    ];

    protected $casts = [
        'asset_specific_limit' => 'decimal:4',
    ];

    public function maintenanceContract(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
