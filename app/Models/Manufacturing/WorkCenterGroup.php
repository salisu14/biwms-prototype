<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenterGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function workCenters(): HasMany
    {
        return $this->hasMany(WorkCenter::class);
    }
}
