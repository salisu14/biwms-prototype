<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

// InventoryPutaway.php - Basic put-away (Method B)
class InventoryPutaway extends Model
{
    protected $fillable = [
        'no',
        'location_id',
        'source_document',
        'source_no',
        'assigned_user_id',
        'status',
        'posting_date'
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryPutawayLine::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    // Create from source document (PO, Sales Return, etc.)
    public static function createFromSource($sourceDoc, $sourceNo)
    {
        // Logic to fetch source lines and create put-away
    }

    // Post - creates item ledger entries and bin entries

    /**
     * @throws \Throwable
     */
    public function post()
    {
        DB::transaction(function() {
            foreach ($this->lines as $line) {
                // Create item ledger entry (receipt)
                // Create warehouse entry (bin placement)
                // Update bin content
            }
            $this->update(['status' => 'Completed', 'posting_date' => now()]);
        });
    }
}
