<?php

// app/Models/WarehouseSetup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseSetup extends Model
{
    use HasFactory;

    protected $table = 'warehouse_setup';

    protected $fillable = [
        'location_mandatory',
        'bin_mandatory',
        'require_pick',
        'require_putaway',
        'require_receive',
        'require_shipment',
        'directed_putaway_and_pick',
        'warehouse_receipt_nos',
        'warehouse_shipment_nos',
        'internal_putaway_nos',
        'internal_pick_nos',
        'bin_capacity_policy',
        'allow_breakbulk',
        'putaway_template_nos',
        'pick_according_to_fefo',
        'default_bin_selection',
    ];

    protected $casts = [
        'location_mandatory' => 'boolean',
        'bin_mandatory' => 'boolean',
        'require_pick' => 'boolean',
        'require_putaway' => 'boolean',
        'require_receive' => 'boolean',
        'require_shipment' => 'boolean',
        'directed_putaway_and_pick' => 'boolean',
        'allow_breakbulk' => 'boolean',
        'pick_according_to_fefo' => 'boolean',
    ];

    // Singleton pattern — BC only has one setup record
    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'location_mandatory' => false,
            'bin_mandatory' => false,
            'require_pick' => false,
            'require_putaway' => false,
            'require_receive' => false,
            'require_shipment' => false,
            'directed_putaway_and_pick' => false,
        ]);
    }

    public static function isLocationRequired(): bool
    {
        return static::instance()->location_mandatory;
    }

    public static function isBinRequired(): bool
    {
        return static::instance()->bin_mandatory;
    }

    public static function isDirectedPutawayAndPick(): bool
    {
        return static::instance()->directed_putaway_and_pick;
    }
}
