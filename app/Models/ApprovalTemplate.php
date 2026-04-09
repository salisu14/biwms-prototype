<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalTemplate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'enabled',
        'document_type',
        'amount_limit',
        'vendor_posting_group_filter',
        'dimension_1_filter',
        'dimension_2_filter',
        'location_filter',
        'due_date_formula',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'amount_limit' => 'decimal:4',
        'dimension_1_filter' => 'array',
        'dimension_2_filter' => 'array',
        'due_date_formula' => 'integer',
    ];

    /**
     * Get the entries (steps) for this approval template.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ApprovalTemplateEntry::class)->orderBy('sequence_no');
    }

    /**
     * Get the vendor posting group filter associated with the template.
     */
    public function vendorPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VendorPostingGroup::class, 'vendor_posting_group_filter');
    }
}
