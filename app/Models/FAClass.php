<?php

namespace App\Models;

use App\Enums\FixedAssetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model for FA Classes
 */
class FAClass extends Model
{
    use HasFactory;

    protected $table = 'fa_classes';

    protected $fillable = ['code', 'name', 'fa_type', 'default_posting_group_id', 'is_active'];

    protected $casts = [
        'fa_type' => FixedAssetType::class,
        'is_active' => 'boolean',
    ];

    public function subclasses(): HasMany
    {
        return $this->hasMany(FASubclass::class, 'fa_class_id');
    }

    public function defaultPostingGroup(): BelongsTo
    {
        return $this->belongsTo(FAPostingGroup::class, 'default_posting_group_id');
    }
}
