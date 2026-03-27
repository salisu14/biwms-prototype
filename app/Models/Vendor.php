<?php
// app/Models/Vendor.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'vendor_code',
    'vendor_name',
    'contact_person',
    'email',
    'phone',
    'mobile',
    'address',
    'city',
    'state',
    'postal_code',
    'country',
    'tax_id',
    'payment_terms',
    'currency',
    'lead_time_days',
    'minimum_order_amount',
    'is_active',
    'notes'
])]
class Vendor extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'vendors';

    protected $casts = [
        'lead_time_days' => 'integer',
        'minimum_order_amount' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Items supplied by this vendor
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemMaster::class, 'vendor_id');
    }

    /**
     * Get full address attribute
     */
    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country
        ]));
    }
}
