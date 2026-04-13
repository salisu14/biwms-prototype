<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for FA Subclasses
 */
class FASubclass extends Model
{
    use HasFactory;

    protected $table = 'fa_subclasses';

    protected $fillable = ['fa_class_id', 'code', 'name', 'default_posting_group_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function faClass(): BelongsTo
    {
        return $this->belongsTo(FAClass::class, 'fa_class_id');
    }

    public function defaultPostingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'default_posting_group_id');
    }
}
