<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxTable extends Model
{
    /** @use HasFactory<\Database\Factories\TaxTableFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'jurisdiction',
        'country_code',
        'state_code',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function brackets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxBracket::class);
    }
}
