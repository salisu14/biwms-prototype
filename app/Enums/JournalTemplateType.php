<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\FixedAssetJournalBatch;
use App\Models\FixedAssetJournalLine;
use App\Models\GeneralJournalBatch;
use App\Models\GeneralJournalLine;
use App\Models\ItemJournalBatch;
use App\Models\ItemJournalLine;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\RecurringJournalBatch;
use App\Models\RecurringJournalLine;
use App\Models\WarehouseJournalBatch;
use App\Models\WarehouseJournalLine;
use App\Services\Posting\FixedAssetJournalPostingRoutine;
use App\Services\Posting\GeneralJournalPostingRoutine;
use App\Services\Posting\ItemJournalPostingRoutine;
use App\Services\Posting\ProductionJournalPostingRoutine;
use App\Services\Posting\RecurringJournalPostingRoutine;
use App\Services\Posting\WarehouseJournalPostingRoutine;

enum JournalTemplateType: string
{
    case GENERAL = 'general';
    case ITEM = 'item';
    case PRODUCTION = 'production';
    case WAREHOUSE = 'warehouse';
    case FIXED_ASSET = 'fixed_asset';
    case RECURRING = 'recurring';
    case PAYROLL = 'payroll';
    case INTERCOMPANY = 'intercompany';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::ITEM => 'Item',
            self::PRODUCTION => 'Production',
            self::WAREHOUSE => 'Warehouse',
            self::FIXED_ASSET => 'Fixed Asset',
            self::RECURRING => 'Recurring',
            self::PAYROLL => 'Payroll',
            self::INTERCOMPANY => 'Intercompany',
        };
    }

    public function getBatchModel(): string
    {
        return match ($this) {
            self::GENERAL => GeneralJournalBatch::class,
            self::ITEM => ItemJournalBatch::class,
            self::PRODUCTION => ProductionJournalBatch::class,
            self::WAREHOUSE => WarehouseJournalBatch::class,
            self::FIXED_ASSET => FixedAssetJournalBatch::class,
            self::RECURRING => RecurringJournalBatch::class,
            default => throw new \InvalidArgumentException("No batch model for {$this->value}"),
        };
    }

    public function getLineModel(): string
    {
        return match ($this) {
            self::GENERAL => GeneralJournalLine::class,
            self::ITEM => ItemJournalLine::class,
            self::PRODUCTION => ProductionJournalLine::class,
            self::WAREHOUSE => WarehouseJournalLine::class,
            self::FIXED_ASSET => FixedAssetJournalLine::class,
            self::RECURRING => RecurringJournalLine::class,
            default => throw new \InvalidArgumentException("No line model for {$this->value}"),
        };
    }

    public function getPostingRoutine(): string
    {
        return match ($this) {
            self::GENERAL => GeneralJournalPostingRoutine::class,
            self::ITEM => ItemJournalPostingRoutine::class,
            self::PRODUCTION => ProductionJournalPostingRoutine::class,
            self::WAREHOUSE => WarehouseJournalPostingRoutine::class,
            self::FIXED_ASSET => FixedAssetJournalPostingRoutine::class,
            self::RECURRING => RecurringJournalPostingRoutine::class,
            default => throw new \InvalidArgumentException("No posting routine for {$this->value}"),
        };
    }
}
