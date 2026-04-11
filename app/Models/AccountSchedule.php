<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountSchedule extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountScheduleLine::class, 'schedule_id')->orderBy('line_no');
    }
}
