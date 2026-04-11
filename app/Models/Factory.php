<?php

namespace App\Models;

use App\Services\OrgEntityService;
use Illuminate\Database\Eloquent\Model;

class Factory extends Model
{
    protected $fillable = ['code', 'name', 'business_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted()
    {
        static::deleting(function ($factory) {
            app(OrgEntityService::class)->deleteFactoryDimension($factory);
        });
    }
}
