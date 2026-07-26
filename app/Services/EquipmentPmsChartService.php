<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EquipmentPmsChartService
{
    private const PREVENTIVE_ACTION_TYPES = [
        'create_preventive_maintenance_schedule',
        'schedule_preventive_maintenance',
        'create_initial_maintenance_schedule',
        'schedule_warranty_inspection',
    ];

    /**
     * @return Collection<int, array{
     *     date: CarbonInterface,
     *     detail: string,
     *     performed_by: string,
     *     source: string,
     *     source_id: int
     * }>
     */
    public function entriesFor(Equipment $equipment): Collection
    {
        $linkedScheduleIds = collect();

        $workOrderEntries = MaintenanceRecommendation::query()
            ->where('equipment_id', $equipment->getKey())
            ->whereIn('suggested_action_type', self::PREVENTIVE_ACTION_TYPES)
            ->with([
                'linkedWorkOrder.acceptedBy',
                'linkedWorkOrder.assignedTo',
                'linkedMaintenanceRequest.latestWorkOrder.acceptedBy',
                'linkedMaintenanceRequest.latestWorkOrder.assignedTo',
            ])
            ->get()
            ->map(function (MaintenanceRecommendation $recommendation) use ($linkedScheduleIds): ?array {
                $workOrder = $recommendation->effectiveLinkedWorkOrder();

                if (! $this->isVerifiedCompletion($workOrder)) {
                    return null;
                }

                if ($recommendation->linked_maintenance_schedule_id) {
                    $linkedScheduleIds->push($recommendation->linked_maintenance_schedule_id);
                }

                return [
                    'date' => $workOrder->completed_at ?: $workOrder->verified_at,
                    'detail' => $this->workOrderDetail($workOrder),
                    'performed_by' => $workOrder->acceptedBy?->name
                        ?: $workOrder->assignedTo?->name
                        ?: 'Not Recorded',
                    'source' => 'work_order',
                    'source_id' => $workOrder->getKey(),
                ];
            })
            ->filter()
            ->unique(fn (array $entry): string => $entry['source'].':'.$entry['source_id']);

        $scheduleEntries = $equipment->maintenanceSchedules()
            ->completed()
            ->whereNotNull('completed_at')
            ->when(
                $linkedScheduleIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $linkedScheduleIds->unique()->all())
            )
            ->with(['completedBy', 'assignedUser'])
            ->get()
            ->map(fn ($schedule): array => [
                'date' => $schedule->completed_at,
                'detail' => $schedule->completion_remarks
                    ?: $schedule->checklist_instructions
                    ?: $schedule->maintenance_type,
                'performed_by' => $schedule->completedBy?->name
                    ?: $schedule->assignedUser?->name
                    ?: 'Not Recorded',
                'source' => 'maintenance_schedule',
                'source_id' => $schedule->getKey(),
            ]);

        return $scheduleEntries
            ->concat($workOrderEntries)
            ->sortBy([
                ['date', 'asc'],
                ['source', 'asc'],
                ['source_id', 'asc'],
            ])
            ->values();
    }

    private function isVerifiedCompletion(?WorkOrder $workOrder): bool
    {
        return $workOrder?->status === 'Completed'
            && $workOrder->completed_at !== null
            && $workOrder->verified_at !== null;
    }

    private function workOrderDetail(WorkOrder $workOrder): string
    {
        return $workOrder->action_performed
            ?: $workOrder->problem_description
            ?: $workOrder->title;
    }
}
