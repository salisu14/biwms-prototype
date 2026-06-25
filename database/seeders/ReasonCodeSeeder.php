<?php

namespace Database\Seeders;

use App\Models\ReasonCode;
use Illuminate\Database\Seeder;

class ReasonCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasonCodes = [
            // ============================================
            // INVENTORY ADJUSTMENTS - POSITIVE
            // ============================================
            [
                'code' => 'PHYS-COUNT',
                'description' => 'Physical Inventory Count - Surplus',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Used when physical count exceeds system quantity',
            ],
            [
                'code' => 'RCPT-ERROR',
                'description' => 'Receipt Error Correction',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Correct under-receipt from vendor',
            ],
            [
                'code' => 'RET-VENDOR',
                'description' => 'Return to Vendor - Reversal',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Reverse previous return that was incorrectly processed',
            ],
            [
                'code' => 'PROD-OVERRUN',
                'description' => 'Production Overrun',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52400',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Excess finished goods produced beyond order quantity',
            ],
            [
                'code' => 'TRANS-IN',
                'description' => 'Transfer In - Inter-warehouse',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52500',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Inbound transfer from another warehouse location',
            ],
            [
                'code' => 'FOUND',
                'description' => 'Inventory Found',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52600',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Items discovered during cleanup or reorganization',
            ],
            [
                'code' => 'REVAL-UP',
                'description' => 'Inventory Revaluation - Upward',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52700',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Standard cost or market value increase',
            ],
            [
                'code' => 'CONSIGN-IN',
                'description' => 'Consignment Stock Received',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '52800',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Vendor consignment stock now owned',
            ],

            // ============================================
            // INVENTORY ADJUSTMENTS - NEGATIVE
            // ============================================
            [
                'code' => 'DAMAGE',
                'description' => 'Damaged Goods',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Items damaged in storage or handling',
            ],
            [
                'code' => 'EXPIRED',
                'description' => 'Expired or Obsolete Stock',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Shelf-life expired or technologically obsolete',
            ],
            [
                'code' => 'SHRINKAGE',
                'description' => 'Inventory Shrinkage',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Unexplained loss, potential theft or misplacement',
            ],
            [
                'code' => 'SCRAP',
                'description' => 'Production Scrap',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53400',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Defective units from production process',
            ],
            [
                'code' => 'SAMPLE',
                'description' => 'Sample or Promotional Use',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53500',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Items used for customer samples or marketing',
            ],
            [
                'code' => 'R&D',
                'description' => 'Research and Development Consumption',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53600',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Materials consumed in R&D projects',
            ],
            [
                'code' => 'MAINT',
                'description' => 'Maintenance Consumption',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53700',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Spare parts used for equipment maintenance',
            ],
            [
                'code' => 'TRANS-OUT',
                'description' => 'Transfer Out - Inter-warehouse',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53800',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Outbound transfer to another warehouse location',
            ],
            [
                'code' => 'REVAL-DOWN',
                'description' => 'Inventory Revaluation - Downward',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53900',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Standard cost decrease or market value write-down',
            ],
            [
                'code' => 'WRITE-OFF',
                'description' => 'Inventory Write-Off',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '54000',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Complete removal of unusable inventory',
            ],

            // ============================================
            // PRODUCTION / MANUFACTURING
            // ============================================
            [
                'code' => 'BOM-CHG',
                'description' => 'BOM Change Adjustment',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '55100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Adjust component inventory due to engineering change',
            ],
            [
                'code' => 'YIELD-VAR',
                'description' => 'Yield Variance',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '55200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Actual production yield differs from standard',
            ],
            [
                'code' => 'REWORK',
                'description' => 'Rework Material Consumption',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '55300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Additional materials for rework of defective units',
            ],
            [
                'code' => 'TOOLING',
                'description' => 'Tooling Wear or Breakage',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '55400',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Consumable tools or dies expended in production',
            ],
            [
                'code' => 'SET-UP',
                'description' => 'Setup Material',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '55500',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Material consumed during machine setup and calibration',
            ],

            // ============================================
            // QUALITY CONTROL
            // ============================================
            [
                'code' => 'QC-REJECT',
                'description' => 'Quality Control Rejection',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '56100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Incoming or in-process goods rejected by QA',
            ],
            [
                'code' => 'QC-HOLD',
                'description' => 'QC Hold Release',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '56200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Release or dispose of quarantined inventory',
            ],
            [
                'code' => 'RECALL',
                'description' => 'Product Recall',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '56300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Customer-returned recalled product',
            ],

            // ============================================
            // WAREHOUSE OPERATIONS
            // ============================================
            [
                'code' => 'PICK-ERROR',
                'description' => 'Picking Error Correction',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '57100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Correct wrong item or quantity picked',
            ],
            [
                'code' => 'PUT-ERR',
                'description' => 'Put-away Error',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '57200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Item placed in wrong bin or location',
            ],
            [
                'code' => 'CYCLE-CNT',
                'description' => 'Cycle Count Adjustment',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '57300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Routine cycle count variance',
            ],
            [
                'code' => 'REPACK',
                'description' => 'Repackaging',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '57400',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Quantity change due to repackaging into different UOM',
            ],

            // ============================================
            // RETURNS
            // ============================================
            [
                'code' => 'CUST-RET',
                'description' => 'Customer Return - Good',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '58100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Return to stock of good condition customer return',
            ],
            [
                'code' => 'CUST-RET-DMG',
                'description' => 'Customer Return - Damaged',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '58200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Customer return in damaged condition, scrap or rework',
            ],
            [
                'code' => 'VEND-RET',
                'description' => 'Return to Vendor',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '58300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Goods returned to supplier',
            ],

            // ============================================
            // SYSTEM / CORRECTIONS
            // ============================================
            [
                'code' => 'SYS-ERR',
                'description' => 'System Error Correction',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '59100',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Correct erroneous system posting',
            ],
            [
                'code' => 'CONV-ERR',
                'description' => 'Unit of Measure Conversion Error',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '59200',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Correct UOM conversion mistake',
            ],
            [
                'code' => 'OPEN-BAL',
                'description' => 'Opening Balance Adjustment',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '59300',
                'inventory_account' => '12000',
                'blocked' => false,
                'comment' => 'Initial stock setup or migration correction',
            ],

            // ============================================
            // BLOCKED / INACTIVE CODES
            // ============================================
            [
                'code' => 'THEFT',
                'description' => 'Theft (Deprecated - Use SHRINKAGE)',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53300',
                'inventory_account' => '12000',
                'blocked' => true,
                'comment' => 'Replaced by SHRINKAGE for audit neutrality',
            ],
            [
                'code' => 'LOST',
                'description' => 'Lost Inventory (Deprecated - Use SHRINKAGE)',
                'default_location_code' => null,
                'default_bin_code' => null,
                'inventory_adjustment_account' => '53300',
                'inventory_account' => '12000',
                'blocked' => true,
                'comment' => 'Replaced by SHRINKAGE',
            ],
        ];

        foreach ($reasonCodes as $code) {
            ReasonCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }
    }
}
