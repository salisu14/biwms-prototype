<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxTable extends Model
{
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

    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class);
    }
}
