<?php

namespace App\Services;

use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\DimensionSet;
use App\Models\DimensionSetEntry;
use App\Models\DimensionSetTreeNode;
use App\Models\GeneralLedgerSetup;
use App\Models\DefaultDimension;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DimensionManagementService
{
    private GeneralLedgerSetup $glSetup;

    public function __construct()
    {
        $this->glSetup = GeneralLedgerSetup::instance();
    }

    /**
     * Get Dimension Set ID from dimension values (BC: GetDimensionSetID)
     * Creates new set if doesn't exist, returns existing ID if found
     */
    public function getDimensionSetID(array $dimensions): int
    {
        // Normalize: remove empty, sort by code for consistent hashing
        $dimensions = collect($dimensions)
            ->filter(fn($v) => !empty($v))
            ->map(fn($v, $k) => [strtoupper($k) => $v])
            ->sortKeys()
            ->toArray();

        if (empty($dimensions)) {
            return 0;
        }

        return DB::transaction(function() use ($dimensions) {
            // Generate hash for lookup
            $hash = md5(json_encode($dimensions));

            // Check if set exists
            $existing = DimensionSet::where('dimension_hash', $hash)->first();
            if ($existing) {
                return $existing->id;
            }

            // Validate all dimensions and values exist
            foreach ($dimensions as $code => $value) {
                $this->validateDimValue($code, $value);
            }

            // Create new Dimension Set
            $set = DimensionSet::create([
                'description' => $this->generateSetDescription($dimensions),
                'dimension_hash' => $hash,
            ]);

            foreach ($dimensions as $code => $value) {
                $dim = Dimension::where('code', $code)->first();
                $dimValue = DimensionValue::where('dimension_id', $dim->id)
                    ->where('code', $value)
                    ->first();

                DimensionSetEntry::create([
                    'dimension_set_id' => $set->id,
                    'dimension_code' => $code,
                    'dimension_value_code' => $value,
                    'dimension_name' => $dim->name,
                    'dimension_value_name' => $dimValue->name,
                ]);
            }

            // Build tree nodes for efficient searching (BC Table 481)
            $this->buildDimensionSetTree($set->id, $dimensions);

            return $set->id;
        });
    }

    /**
     * Get dimension values from Dimension Set ID (BC: GetDimensionSet)
     */
    public function getDimensionSet(int $dimensionSetId): Collection
    {
        if ($dimensionSetId === 0) {
            return collect();
        }

        return Cache::remember("dim_set_{$dimensionSetId}", 3600, function() use ($dimensionSetId) {
            return DimensionSetEntry::where('dimension_set_id', $dimensionSetId)
                ->get()
                ->mapWithKeys(function($entry) {
                    return [$entry->dimension_code => [
                        'code' => $entry->dimension_value_code,
                        'name' => $entry->dimension_value_name,
                    ]];
                });
        });
    }

    /**
     * Update Dimension Set and return new ID (immutable pattern)
     */
    public function editDimensionSet(int $dimensionSetId, array $changes): int
    {
        $current = $this->getDimensionSet($dimensionSetId)->toArray();
        $merged = array_merge($current, $changes);
        return $this->getDimensionSetID($merged);
    }

    /**
     * Get Default Dimensions for entity (BC: GetDefaultDim)
     */
    public function getDefaultDimensions(string $tableId, string $no): array
    {
        return DefaultDimension::forEntity($tableId, $no)
            ->where('blocked', false)
            ->get()
            ->mapWithKeys(function($dim) {
                return [$dim->dimension_code => [
                    'value_code' => $dim->dimension_value_code,
                    'value_posting' => $dim->value_posting,
                ]];
            })
            ->toArray();
    }

    /**
     * Apply default dimensions to document (BC: ApplyDefaultDim)
     */
    public function applyDefaultDimensions(array &$targetDimensions, string $tableId, string $no): void
    {
        $defaults = $this->getDefaultDimensions($tableId, $no);

        foreach ($defaults as $dimCode => $setting) {
            switch ($setting['value_posting']->value) {
                case 'same_code':
                    $targetDimensions[$dimCode] = $setting['value_code'];
                    break;
                case 'no_code':
                    unset($targetDimensions[$dimCode]);
                    break;
                case 'code_mandatory':
                    if (empty($targetDimensions[$dimCode])) {
                        throw new \RuntimeException("Dimension {$dimCode} is mandatory");
                    }
                    break;
                case 'same_code_mandatory':
                    if (empty($targetDimensions[$dimCode])) {
                        $targetDimensions[$dimCode] = $setting['value_code'];
                    }
                    if ($targetDimensions[$dimCode] !== $setting['value_code']) {
                        throw new \RuntimeException("Dimension {$dimCode} must be {$setting['value_code']}");
                    }
                    break;
            }
        }
    }

    /**
     * Validate dimension combination (BC: CheckDimComb)
     */
    public function validateDimensionCombination(array $dimensions): bool
    {
        $dimCodes = array_keys(array_filter($dimensions));

        foreach ($dimCodes as $code1) {
            foreach ($dimCodes as $code2) {
                if ($code1 >= $code2) continue;

                $combination = DB::table('dimension_combinations')
                    ->where('dimension_1_code', $code1)
                    ->where('dimension_2_code', $code2)
                    ->first();

                if ($combination?->combination_type === 'blocked') {
                    throw new \RuntimeException("Combination of dimensions {$code1} and {$code2} is blocked");
                }

                if ($combination?->combination_type === 'limited') {
                    $this->validateLimitedCombination($combination->id, $dimensions[$code1], $dimensions[$code2]);
                }
            }
        }

        return true;
    }

    /**
     * Get Shortcut Dimensions 1-8 from Dimension Set (BC: GetShortcutDimensions)
     */
    public function getShortcutDimensions(int $dimensionSetId): array
    {
        $shortcuts = $this->glSetup->shortcut_dimensions;
        $dimensions = $this->getDimensionSet($dimensionSetId);

        $result = [];
        for ($i = 1; $i <= 8; $i++) {
            $dimCode = $shortcuts[$i] ?? null;
            $result["shortcut_dimension_{$i}_code"] = $dimCode && isset($dimensions[$dimCode])
                ? $dimensions[$dimCode]['code']
                : null;
        }

        return $result;
    }

    /**
     * Change Global Dimensions (BC: ChangeGlobalDimensions)
     * WARNING: Heavy operation that updates all posted entries
     */
    public function changeGlobalDimensions(?string $newDim1, ?string $newDim2, string $mode = 'sequential'): void
    {
        $currentSetup = GeneralLedgerSetup::instance();

        DB::transaction(function() use ($newDim1, $newDim2, $mode, $currentSetup) {
            // Validate new dimensions exist
            if ($newDim1 && !Dimension::where('code', $newDim1)->exists()) {
                throw new \RuntimeException("Dimension {$newDim1} does not exist");
            }
            if ($newDim2 && !Dimension::where('code', $newDim2)->exists()) {
                throw new \RuntimeException("Dimension {$newDim2} does not exist");
            }

            $oldDim1 = $currentSetup->global_dimension_1_code;
            $oldDim2 = $currentSetup->global_dimension_2_code;

            if ($mode === 'sequential') {
                // Update all ledger entry tables
                $this->updateGlobalDimensionsInEntries($oldDim1, $newDim1, $oldDim2, $newDim2);
            } else {
                // Parallel mode would queue jobs
                dispatch(new \App\Jobs\ChangeGlobalDimensionsJob($oldDim1, $newDim1, $oldDim2, $newDim2));
                return;
            }

            // Update setup
            $currentSetup->update([
                'global_dimension_1_code' => $newDim1,
                'global_dimension_2_code' => $newDim2,
            ]);

            // Update dimension types
            Dimension::where('code', $oldDim1)->update(['dimension_type' => 'regular', 'global_dimension_no' => null]);
            Dimension::where('code', $oldDim2)->update(['dimension_type' => 'regular', 'global_dimension_no' => null]);
            if ($newDim1) Dimension::where('code', $newDim1)->update(['dimension_type' => 'global', 'global_dimension_no' => 1]);
            if ($newDim2) Dimension::where('code', $newDim2)->update(['dimension_type' => 'global', 'global_dimension_no' => 2]);
        });
    }

    /**
     * Validate dimension value exists and is valid
     */
    public function validateDimValue(string $dimensionCode, string $valueCode): void
    {
        $dimension = Dimension::where('code', $dimensionCode)->first();
        if (!$dimension) {
            throw new \RuntimeException("Dimension {$dimensionCode} does not exist");
        }

        $value = DimensionValue::where('dimension_id', $dimension->id)
            ->where('code', $valueCode)
            ->first();

        if (!$value) {
            throw new \RuntimeException("Dimension Value {$valueCode} does not exist for Dimension {$dimensionCode}");
        }

        if ($value->blocked) {
            throw new \RuntimeException("Dimension Value {$valueCode} is blocked");
        }

        if ($value->starting_date && $value->starting_date > now()) {
            throw new \RuntimeException("Dimension Value {$valueCode} is not yet valid");
        }

        if ($value->ending_date && $value->ending_date < now()) {
            throw new \RuntimeException("Dimension Value {$valueCode} has expired");
        }
    }

    /**
     * Show Dimensions as string for display
     */
    public function getDimensionDescription(int $dimensionSetId): string
    {
        if ($dimensionSetId === 0) return '';
        return DimensionSet::find($dimensionSetId)?->display_string ?? '';
    }

    /**
     * Copy dimension set to new document
     */
    public function copyDimensionSet(int $fromSetId, string $toTableNo, string $toDocNo): int
    {
        return $fromSetId; // In BC this creates new set if needed, but ID can stay same
    }

    /**
     * Compare two dimension sets
     */
    public function dimensionSetsEqual(int $setId1, int $setId2): bool
    {
        if ($setId1 === $setId2) return true;
        if ($setId1 === 0 || $setId2 === 0) return false;

        $hash1 = DimensionSet::find($setId1)?->dimension_hash;
        $hash2 = DimensionSet::find($setId2)?->dimension_hash;

        return $hash1 && $hash2 && $hash1 === $hash2;
    }

    /**
     * Get all global dimensions (1-2)
     */
    public function getGlobalDimensions(): array
    {
        return [
            1 => $this->glSetup->global_dimension_1_code,
            2 => $this->glSetup->global_dimension_2_code,
        ];
    }

    private function validateLimitedCombination(int $combinationId, string $value1, string $value2): void
    {
        $blocked = DB::table('dimension_value_combinations')
            ->where('dimension_combination_id', $combinationId)
            ->where('dimension_1_value_code', $value1)
            ->where('dimension_2_value_code', $value2)
            ->where('blocked', true)
            ->exists();

        if ($blocked) {
            throw new \RuntimeException("This combination of dimension values is blocked");
        }
    }

    private function buildDimensionSetTree(int $setId, array $dimensions): void
    {
        $parentId = 0;
        foreach ($dimensions as $code => $value) {
            $dimValue = DimensionValue::whereHas('dimension', fn($q) => $q->where('code', $code))
                ->where('code', $value)
                ->first();

            if ($dimValue) {
                DimensionSetTreeNode::create([
                    'parent_dimension_set_id' => $parentId,
                    'dimension_value_id' => $dimValue->id,
                    'dimension_set_id' => $setId,
                    'in_use' => true,
                ]);
                $parentId = $dimValue->id;
            }
        }
    }

    private function generateSetDescription(array $dimensions): string
    {
        return collect($dimensions)
            ->map(fn($v, $k) => "{$k}:{$v}")
            ->implode(', ');
    }

    private function updateGlobalDimensionsInEntries(?string $oldDim1, ?string $newDim1, ?string $oldDim2, ?string $newDim2): void
    {
        // This would update all ledger entry tables
        // Tables: gl_entries, customer_ledger_entries, vendor_ledger_entries, item_ledger_entries, etc.

        $tables = [
            'general_ledger_entries',
            'customer_ledger_entries',
            'vendor_ledger_entries',
            'item_ledger_entries',
            'sales_shipment_headers',
            'sales_invoice_headers',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'global_dimension_1_code')) {
                DB::table($table)->update([
                    'global_dimension_1_code' => DB::raw("CASE WHEN global_dimension_1_code = '{$oldDim1}' THEN '{$newDim1}' ELSE global_dimension_1_code END"),
                    'global_dimension_2_code' => DB::raw("CASE WHEN global_dimension_2_code = '{$oldDim2}' THEN '{$newDim2}' ELSE global_dimension_2_code END"),
                ]);
            }
        }
    }
}
