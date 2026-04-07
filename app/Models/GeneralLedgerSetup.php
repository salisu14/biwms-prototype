<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralLedgerSetup extends Model
{
    use HasFactory;

    protected $table = 'general_ledger_setup';
    public $timestamps = true;

    protected $fillable = [
        'global_dimension_1_code', 'global_dimension_2_code',
        'shortcut_dimension_3_code', 'shortcut_dimension_4_code',
        'shortcut_dimension_5_code', 'shortcut_dimension_6_code',
        'shortcut_dimension_7_code', 'shortcut_dimension_8_code',
        'lc_code', 'company_name'
    ];

    /**
     * Get all shortcut dimensions as array [1 => code, 2 => code...]
     */
    public function getShortcutDimensionsAttribute(): array
    {
        return [
            1 => $this->global_dimension_1_code,
            2 => $this->global_dimension_2_code,
            3 => $this->shortcut_dimension_3_code,
            4 => $this->shortcut_dimension_4_code,
            5 => $this->shortcut_dimension_5_code,
            6 => $this->shortcut_dimension_6_code,
            7 => $this->shortcut_dimension_7_code,
            8 => $this->shortcut_dimension_8_code,
        ];
    }

    public static function instance(): self
    {
        return static::firstOrCreate(['company_name' => 'Default Company']);
    }
}
