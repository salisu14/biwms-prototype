<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionAlternateResource;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\WorkCenter;
use Carbon\CarbonInterface;

class ProductionAlternateResourceSelectionService
{
    public function __construct(private readonly ProductionCapacityCalendarService $calendarService) {}

    /**
     * @return array{work_center: WorkCenter|null, machine_center: MachineCenter|null, uses_alternate: bool, reason: array<string, mixed>}
     */
    public function select(ProductionOrderRoutingLine $routingLine, CarbonInterface $start, int $durationMinutes, CarbonInterface $horizonEnd): array
    {
        $routingLine->loadMissing(['workCenter', 'machineCenter.workCenter']);

        $candidates = $this->candidates($routingLine);
        $feasibleCandidates = [];

        foreach ($candidates as $candidate) {
            $workCenter = $candidate['work_center'];
            $machineCenter = $candidate['machine_center'];

            if (! $workCenter) {
                continue;
            }

            $slot = $this->calendarService->nextForwardSlot($workCenter, $machineCenter?->id, $start, $durationMinutes, $horizonEnd);

            if ($slot !== null) {
                $feasibleCandidates[] = [
                    ...$candidate,
                    'slot_start' => $slot[0],
                ];
            }
        }

        if ($feasibleCandidates !== []) {
            usort($feasibleCandidates, fn (array $a, array $b): int => $a['slot_start']->getTimestamp() <=> $b['slot_start']->getTimestamp()
                ?: ((int) $a['uses_alternate'] <=> (int) $b['uses_alternate']));

            $selected = $feasibleCandidates[0];

            return [
                'work_center' => $selected['work_center'],
                'machine_center' => $selected['machine_center'],
                'uses_alternate' => (bool) $selected['uses_alternate'],
                'reason' => [
                    'selected' => $selected['label'],
                    'uses_alternate' => (bool) $selected['uses_alternate'],
                    'reason' => $selected['uses_alternate']
                        ? 'Configured alternate provides the earliest feasible capacity slot.'
                        : 'Primary resource has the earliest feasible capacity slot.',
                ],
            ];
        }

        return [
            'work_center' => $routingLine->workCenter,
            'machine_center' => $routingLine->machineCenter,
            'uses_alternate' => false,
            'reason' => ['reason' => 'No primary or configured alternate resource has a feasible slot in the planning horizon.'],
        ];
    }

    /**
     * @return array<int, array{work_center: WorkCenter|null, machine_center: MachineCenter|null, uses_alternate: bool, label: string}>
     */
    private function candidates(ProductionOrderRoutingLine $routingLine): array
    {
        $primaryWorkCenter = $routingLine->machineCenter?->workCenter ?? $routingLine->workCenter;
        $candidates = [[
            'work_center' => $primaryWorkCenter,
            'machine_center' => $routingLine->machineCenter,
            'uses_alternate' => false,
            'label' => $routingLine->machineCenter?->code ?? $primaryWorkCenter?->code ?? 'unassigned',
        ]];

        $alternates = ProductionAlternateResource::query()
            ->with(['alternateWorkCenter', 'alternateMachineCenter.workCenter'])
            ->where('is_active', true)
            ->where(function ($query) use ($routingLine, $primaryWorkCenter): void {
                if ($routingLine->machine_center_id) {
                    $query->where('primary_machine_center_id', $routingLine->machine_center_id);
                }

                if ($primaryWorkCenter?->id) {
                    $query->orWhere('primary_work_center_id', $primaryWorkCenter->id);
                }
            })
            ->where(function ($query): void {
                $today = now()->toDateString();
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today);
            })
            ->where(function ($query): void {
                $today = now()->toDateString();
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($alternates as $alternate) {
            $alternateMachine = $alternate->alternateMachineCenter;
            $alternateWorkCenter = $alternateMachine?->workCenter ?? $alternate->alternateWorkCenter;

            $candidates[] = [
                'work_center' => $alternateWorkCenter,
                'machine_center' => $alternateMachine,
                'uses_alternate' => true,
                'label' => $alternateMachine?->code ?? $alternateWorkCenter?->code ?? 'alternate',
            ];
        }

        return $candidates;
    }
}
