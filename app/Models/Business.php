<?php

namespace App\Models;

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
            app(\App\Services\OrgEntityService::class)->deleteBusinessDimension($business);
        });
    }
}
