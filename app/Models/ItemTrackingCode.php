<?php

// app/Models/ItemTrackingCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemTrackingCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'snspecific_tracking',
        'lotspecific_tracking',
        'lot_wholesale_tracking',
        'man_expiration_date_entry_reqd',
        'man_expiration_date_on_receipt',
        'strict_expiration_posting',
        'allow_expiration_correction',
        'lot_info_purchase_inbound',
        'lot_info_purchase_outbound',
        'lot_info_sales_inbound',
        'lot_info_sales_outbound',
        'sn_info_purchase_inbound',
        'sn_info_purchase_outbound',
        'sn_info_sales_inbound',
        'sn_info_sales_outbound',
    ];

    protected $casts = [
        'snspecific_tracking' => 'boolean',
        'lotspecific_tracking' => 'boolean',
        'lot_wholesale_tracking' => 'boolean',
        'man_expiration_date_entry_reqd' => 'boolean',
        'man_expiration_date_on_receipt' => 'boolean',
        'strict_expiration_posting' => 'boolean',
        'allow_expiration_correction' => 'boolean',
        'lot_info_purchase_inbound' => 'boolean',
        'lot_info_purchase_outbound' => 'boolean',
        'lot_info_sales_inbound' => 'boolean',
        'lot_info_sales_outbound' => 'boolean',
        'sn_info_purchase_inbound' => 'boolean',
        'sn_info_purchase_outbound' => 'boolean',
        'sn_info_sales_inbound' => 'boolean',
        'sn_info_sales_outbound' => 'boolean',
    ];

    public function getRequiresLotAttribute(): bool
    {
        return $this->lotspecific_tracking || $this->lot_wholesale_tracking;
    }

    public function getRequiresSerialAttribute(): bool
    {
        return $this->snspecific_tracking;
    }
}
