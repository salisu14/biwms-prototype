<?php

namespace App\Enums;

enum ExpenseCategoryEnum: string
{
    // Direct Expenses
    case RAW_MATERIALS = 'raw_materials';
    case DIRECT_LABOR = 'direct_labor';
    case DIRECT_OVERHEAD = 'direct_overhead';
    case SUBCONTRACTING = 'subcontracting';

    // Indirect Expenses - Manufacturing
    case FACTORY_RENT = 'factory_rent';
    case FACTORY_UTILITIES = 'factory_utilities';
    case DEPRECIATION_PLANT = 'depreciation_plant';
    case MAINTENANCE_PLANT = 'maintenance_plant';

    // Indirect Expenses - Administrative
    case SALARIES_ADMIN = 'salaries_admin';
    case OFFICE_RENT = 'office_rent';
    case OFFICE_SUPPLIES = 'office_supplies';
    case LEGAL_FEES = 'legal_fees';
    case AUDIT_FEES = 'audit_fees';

    // Indirect Expenses - Selling & Distribution
    case SALES_COMMISSION = 'sales_commission';
    case ADVERTISING = 'advertising';
    case TRANSPORT_OUT = 'transport_out';
    case PACKAGING = 'packaging';

    // Special Categories
    case INVENTORY_ADJUSTMENT = 'inventory_adjustment';
    case INVENTORY_WRITE_OFF = 'inventory_write_off';
    case FOREIGN_EXCHANGE_LOSS = 'foreign_exchange_loss';
    case BANK_CHARGES = 'bank_charges';

    public function label(): string
    {
        return match ($this) {
            self::RAW_MATERIALS => 'Raw Materials',
            self::DIRECT_LABOR => 'Direct Labor',
            self::DIRECT_OVERHEAD => 'Direct Overhead',
            self::SUBCONTRACTING => 'Subcontracting',
            self::FACTORY_RENT => 'Factory Rent',
            self::FACTORY_UTILITIES => 'Factory Utilities',
            self::DEPRECIATION_PLANT => 'Depreciation - Plant & Machinery',
            self::MAINTENANCE_PLANT => 'Maintenance - Plant',
            self::SALARIES_ADMIN => 'Salaries - Administration',
            self::OFFICE_RENT => 'Office Rent',
            self::OFFICE_SUPPLIES => 'Office Supplies',
            self::LEGAL_FEES => 'Legal & Professional Fees',
            self::AUDIT_FEES => 'Audit Fees',
            self::SALES_COMMISSION => 'Sales Commission',
            self::ADVERTISING => 'Advertising & Marketing',
            self::TRANSPORT_OUT => 'Transport Outward',
            self::PACKAGING => 'Packaging Materials',
            self::INVENTORY_ADJUSTMENT => 'Inventory Adjustment',
            self::INVENTORY_WRITE_OFF => 'Inventory Write-off',
            self::FOREIGN_EXCHANGE_LOSS => 'Foreign Exchange Loss',
            self::BANK_CHARGES => 'Bank Charges',
        };
    }

    public function isDirect(): bool
    {
        return in_array($this, [self::RAW_MATERIALS, self::DIRECT_LABOR, self::DIRECT_OVERHEAD, self::SUBCONTRACTING], true);
    }

    public function isManufacturingOverhead(): bool
    {
        return in_array($this, [
            self::FACTORY_RENT, self::FACTORY_UTILITIES,
            self::DEPRECIATION_PLANT, self::MAINTENANCE_PLANT,
        ], true);
    }

    public function isAdministrative(): bool
    {
        return in_array($this, [
            self::SALARIES_ADMIN, self::OFFICE_RENT,
            self::OFFICE_SUPPLIES, self::LEGAL_FEES, self::AUDIT_FEES,
        ], true);
    }

    public function isSelling(): bool
    {
        return in_array($this, [
            self::SALES_COMMISSION, self::ADVERTISING,
            self::TRANSPORT_OUT, self::PACKAGING,
        ], true);
    }
}
