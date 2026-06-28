<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for FA Locations
 */
class FALocation extends Model
{
    use HasFactory;

    protected $table = 'fa_locations';

    protected $fillable = ['code', 'name', 'location_id', 'responsible_employee_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_employee_id'); // Assuming User or Employee model
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
