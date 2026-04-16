<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// WarehousePutaway.php - Advanced put-away (Method D)
class WarehousePutaway extends Model
{
    protected $fillable = ['no', 'location_id', 'warehouse_receipt_id', 'assigned_user_id', 'status', 'sorting_method'];

    public function lines()
    {
        return $this->hasMany(WarehousePutawayLine::class);
    }

    public function takeLines()
    {
        return $this->lines()->where('action_type', 'Take');
    }

    public function placeLines()
    {
        return $this->lines()->where('action_type', 'Place');
    }

    // Register - only creates warehouse entries (items already received)

    /**
     * @throws \Throwable
     */
    public function register()
    {
        DB::transaction(function() {
            foreach ($this->lines as $line) {
                if ($line->action_type === 'Place') {
                    // Create warehouse entry moving from receipt bin to storage bin
                    // Update bin content
                }
            }
            $this->update(['status' => 'Completed']);
        });
    }

    // Split line (when placing in multiple bins)
    public function splitLine($lineId, $quantity)
    {
        $line = $this->lines()->find($lineId);
        $newLine = $line->replicate();
        $newLine->quantity = $quantity;
        $newLine->qty_to_handle = $quantity;
        $newLine->save();

        $line->quantity -= $quantity;
        $line->qty_to_handle -= $quantity;
        $line->save();
    }
}
