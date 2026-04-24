<?php

namespace App\Models;

use App\Enums\ValuePosting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultDimension extends Model
{
    protected $table = 'default_dimensions';

    protected $fillable = [
        'table_id',
        'no',
        'dimension_code',      // Stores the Code of the Dimension (e.g., 'PROJECT')
        'dimension_value_code', // Stores the Code of the Value (e.g., 'PRJ001')
        'value_posting',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'value_posting' => ValuePosting::class,

        // Explicit casts to ensure type safety
        'table_id' => 'string',
        'no' => 'int',
    ];

    /**
     * Boot logic to ensure data integrity (e.g., Uppercase Codes).
     */
    protected static function booted(): void
    {
        static::saving(function (DefaultDimension $record) {
            // Force Uppercase to match Dimension/DimensionValue tables
            if ($record->dimension_code) {
                $record->dimension_code = strtoupper($record->dimension_code);
            }
            if ($record->dimension_value_code) {
                $record->dimension_value_code = strtoupper($record->dimension_value_code);
            }
        });
    }

    // --- Relationships ---

    /**
     * Relates to the Dimension header using the 'code' as the foreign key.
     * This assumes 'dimension_code' in this table matches 'code' in 'dimensions' table.
     */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_code', 'code');
    }

    /**
     * Relates to the specific Dimension Value using the 'code' as the foreign key.
     * This assumes 'dimension_value_code' matches 'code' in 'dimension_values' table.
     */
    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class, 'dimension_value_code', 'code');
    }

    // --- Scopes ---

    /**
     * Scope to find default dimensions for a specific entity type and ID.
     * Example: DefaultDimension::forEntity('vendors', 1)->get();
     */
    public function scopeForEntity($query, string $tableId, int $no)
    {
        return $query->where('table_id', $tableId)->where('no', $no);
    }

    /**
     * Scope to include only active (not blocked) rules.
     */
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    // --- Accessors ---

    /**
     * Helper to get a readable key for this rule.
     */
    public function getCompositeKeyAttribute(): string
    {
        return $this->table_id . '_' . $this->no . '_' . $this->dimension_code;
    }
}
