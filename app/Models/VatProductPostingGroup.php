<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatProductPostingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
    ];

    public function vatPostingSetups()
    {
        return $this->hasMany(VatPostingSetup::class);
    }
}
