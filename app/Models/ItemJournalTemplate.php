<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemJournalTemplate extends Model
{
    use HasFactory;

    protected $table = 'item_journal_templates';

    protected $fillable = [
        'name',
        'description',
        'default_entry_type',
        'number_series_id',
        'posting_number_series_id',
        'source_code',
        'reason_code',
        'default_inventory_account_id',
        'force_inventory_account',
        'item_tracking_mandatory',
        'lot_mandatory',
        'serial_no_mandatory',
        'expiration_date_mandatory',
        'warehouse_location_mandatory',
        'bin_mandatory',
        'check_warehouse_availability',
        'allow_negative_inventory',
        'costing_per_entry',
        'mandatory_dimensions',
        'default_dimensions',
        'allowed_item_categories',
        'blocked_item_nos',
        'test_report_before_posting',
        'is_active',
    ];

    protected $casts = [
        'force_inventory_account' => 'boolean',
        'item_tracking_mandatory' => 'boolean',
        'lot_mandatory' => 'boolean',
        'serial_no_mandatory' => 'boolean',
        'expiration_date_mandatory' => 'boolean',
        'warehouse_location_mandatory' => 'boolean',
        'bin_mandatory' => 'boolean',
        'check_warehouse_availability' => 'boolean',
        'allow_negative_inventory' => 'boolean',
        'costing_per_entry' => 'boolean',
        'test_report_before_posting' => 'boolean',
        'is_active' => 'boolean',
        'mandatory_dimensions' => 'array',
        'default_dimensions' => 'array',
        'allowed_item_categories' => 'array',
        'blocked_item_nos' => 'array',
        'default_entry_type' => JournalLineType::class,
    ];

    public function numberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'number_series_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemJournalBatch::class, 'template_id');
    }

    public function postingNumberSeries(): BelongsTo
    {
        return $this->belongsTo(NumberSeries::class, 'posting_number_series_id');
    }

    public function defaultInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_inventory_account_id');
    }

    public function requiresItemTracking(): bool
    {
        return $this->item_tracking_mandatory
            || $this->lot_mandatory
            || $this->serial_no_mandatory;
    }

    public function isValidEntryType(JournalLineType $type): bool
    {
        return in_array($type, [
            JournalLineType::POSITIVE_ADJUSTMENT,
            JournalLineType::NEGATIVE_ADJUSTMENT,
            JournalLineType::PURCHASE,
            JournalLineType::SALE,
            JournalLineType::TRANSFER,
            JournalLineType::CONSUMPTION,
            JournalLineType::OUTPUT,
        ]);
    }
}
