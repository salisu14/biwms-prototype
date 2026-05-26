<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverheadCostCategory extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];

    public function actualOverheadCosts()
    {
        return $this->hasMany(ActualOverheadCost::class, 'cost_type_code', 'code');
    }
}
