<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSchedule extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccountScheduleLine::class, 'schedule_id')->orderBy('line_no');
    }
}
