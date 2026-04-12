<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\JournalTemplateType;
use App\Models\GeneralJournalBatch;
use App\Models\ItemJournalBatch;
use App\Models\ProductionJournalBatch;
use App\Models\RecurringJournalBatch;
use App\Models\WarehouseJournalBatch;
use Illuminate\Support\Collection;

class JournalPostingService
{
    public function post(object $batch): PostingResult
    {
        $routine = $this->resolveRoutine($batch);

        return $routine->post($batch);
    }

    public function validate(object $batch): array
    {
        $routine = $this->resolveRoutine($batch);

        return $routine->validate($batch);
    }

    public function preview(object $batch): Collection
    {
        $routine = $this->resolveRoutine($batch);

        return $routine->preview($batch);
    }

    public function reverse(object $batch, string $reason): void
    {
        $routine = $this->resolveRoutine($batch);
        $routine->reverse($batch, $reason);
    }

    private function resolveRoutine(object $batch): PostingRoutineInterface
    {
        $type = match (get_class($batch)) {
            GeneralJournalBatch::class => JournalTemplateType::GENERAL,
            ItemJournalBatch::class => JournalTemplateType::ITEM,
            ProductionJournalBatch::class => JournalTemplateType::PRODUCTION,
            WarehouseJournalBatch::class => JournalTemplateType::WAREHOUSE,
            RecurringJournalBatch::class => JournalTemplateType::RECURRING,
            default => throw new \InvalidArgumentException('Unknown batch type: '.get_class($batch)),
        };

        $routineClass = $type->getPostingRoutine();

        return app($routineClass);
    }
}
