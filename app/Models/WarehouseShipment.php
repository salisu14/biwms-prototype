<?php

// app/Models/WarehouseShipment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'location_id',
        'source_document',
        'source_document_id',
        'source_document_number',
        'customer_id',
        'shipping_agent_code',
        'shipping_agent_service_code',
        'external_document_number',
        'status',
        'assigned_user_id',
        'shipment_date',
        'planned_delivery_date',
        'posted_date',
    ];

    protected $casts = [
        'shipment_date' => 'date',
        'planned_delivery_date' => 'date',
        'posted_date' => 'datetime',
    ];

    // Relationships
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseShipmentLine::class);
    }

    // Status helpers
    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isShipped(): bool
    {
        return $this->status === 'SHIPPED';
    }

    public function canPost(): bool
    {
        return in_array($this->status, ['RELEASED', 'PARTIALLY_SHIPPED']);
    }

    // Post the shipment (creates item ledger entries)
    public function post(): bool
    {
        if (! $this->canPost()) {
            return false;
        }

        // Implementation would:
        // 1. Create negative item ledger entries
        // 2. Reduce inventory
        // 3. Update status

        $this->update([
            'status' => 'SHIPPED',
            'posted_date' => now(),
        ]);

        return true;
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    // Scope
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['OPEN', 'RELEASED', 'PARTIALLY_SHIPPED']);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
