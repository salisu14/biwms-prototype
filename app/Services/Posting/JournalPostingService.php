<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\JournalTemplateType;
use App\Models\GeneralJournalBatch;
use App\Models\ItemJournalBatch;
use App\Models\ProductionJournalBatch;
use App\Models\RecurringJournalBatch;
use App\Models\WarehouseJournalBatch;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class JournalPostingService
{
    private readonly AuditTrailService $auditTrailService;

    public function __construct(?AuditTrailService $auditTrailService = null)
    {
        $this->auditTrailService = $auditTrailService ?? app(AuditTrailService::class);
    }

    public function post(object $batch): PostingResult
    {
        $routine = $this->resolveRoutine($batch);

        $result = $routine->post($batch);

        if ($batch instanceof Model) {
            $this->auditTrailService->recordGeneric(
                eventType: 'posting',
                action: 'journal_posted',
                auditable: $batch,
                documentType: class_basename($batch),
                documentNo: $this->auditTrailService->documentNoFor($batch),
                description: class_basename($batch).' posted',
                metadata: [
                    'success' => $result->success,
                    'document_no' => $result->documentNo,
                    'posted_entry_count' => count($result->postedEntries),
                ],
            );
        }

        return $result;
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

        if ($batch instanceof Model) {
            $this->auditTrailService->recordGeneric(
                eventType: 'reversal',
                action: 'journal_reversed',
                auditable: $batch,
                documentType: class_basename($batch),
                documentNo: $this->auditTrailService->documentNoFor($batch),
                description: class_basename($batch).' reversed',
                metadata: [
                    'reason' => $reason,
                ],
            );
        }
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
