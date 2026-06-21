<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatBusinessPostingGroup extends Model
{

    protected $fillable = [
        'code',
        'description',
        'blocked'
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    public function vatPostingSetups()
    {
        return $this->hasMany(VatPostingSetup::class);
    }
}
