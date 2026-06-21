<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FlushingMethod;
use App\Models\Manufacturing\ProductionOrder;

class ProductionOrderObserver
{
    public function __construct(
        private readonly ProductionJournalCreationService $journalCreationService
    ) {}

    public function released(ProductionOrder $order): void
    {
        // Create consumption journal lines based on flushing method
        foreach ($order->components as $component) {
            $flushingMethod = $component->flushingMethod();

            if ($flushingMethod === FlushingMethod::FORWARD) {
                // Create and post immediately
                $this->journalCreationService->createAndPostConsumption($component);
            } elseif ($flushingMethod === FlushingMethod::PICK) {
                // Create warehouse pick request
                $this->journalCreationService->createPickRequest($component);
            } elseif ($flushingMethod === FlushingMethod::MANUAL) {
                // Pre-populate journal batch for manual entry
                $this->journalCreationService->prepareConsumptionLine($component);
            }
        }
    }

    public function statusChangedToFinished(ProductionOrder $order): void
    {
        // Handle backward flushing
        foreach ($order->components as $component) {
            if ($component->flushingMethod() === FlushingMethod::BACKWARD) {
                $this->journalCreationService->createAndPostConsumption($component, true); // Actual quantity
            }
        }

        // Post output
        foreach ($order->lines as $line) {
            $this->journalCreationService->createAndPostOutput($line);
        }
    }
}
