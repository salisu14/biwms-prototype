<?php

namespace App\Models;

use App\Services\OrgEntityService;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function factories()
    {
        return $this->hasMany(Factory::class);
    }

    protected static function booted()
    {
        static::deleting(function ($business) {
            app(OrgEntityService::class)->deleteBusinessDimension($business);
        });
    }
}
