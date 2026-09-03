<?php

namespace App\Models;

use App\Services\OrgEntityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_businesses')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    protected static function booted()
    {
        static::deleting(function ($business) {
            app(OrgEntityService::class)->deleteBusinessDimension($business);
        });
    }
}
