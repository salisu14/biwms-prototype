<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverheadCostCategory extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (OverheadCostCategory $category): void {
            $category->code = strtoupper(trim((string) $category->code));
            $category->name = trim((string) $category->name);
        });
    }

    public function actualOverheadCosts()
    {
        return $this->hasMany(ActualOverheadCost::class, 'cost_type_code', 'code');
    }
}
