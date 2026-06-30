<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;

class MaintenanceRecommendationEngine
{
    /**
     * @return array{equipment_checked: int, created: int, updated: int}
     */
    public function generateForAllEquipment(): array
    {
        $summary = [
            'equipment_checked' => 0,
            'created' => 0,
            'updated' => 0,
        ];

        Equipment::query()
            ->with(['workOrders', 'maintenanceSchedules'])
            ->each(function (Equipment $equipment) use (&$summary): void {
                $result = $this->generateForEquipment($equipment);

                $summary['equipment_checked']++;
                $summary['created'] += $result['created'];
                $summary['updated'] += $result['updated'];
            });

        return $summary;
    }

    /**
     * @return array{created: int, updated: int}
     */
    public function generateForEquipment(Equipment $equipment): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
        ];

        foreach ($this->matchingRules($equipment->refresh()) as $recommendation) {
            $existing = MaintenanceRecommendation::query()
                ->open()
                ->where('equipment_id', $equipment->id)
                ->where('rule_key', $recommendation['rule_key'])
                ->first();

            if ($existing) {
                $existing->forceFill(array_merge($recommendation, [
                    'generated_at' => now(),
                ]))->save();

                $result['updated']++;

                continue;
            }

            MaintenanceRecommendation::create(array_merge($recommendation, [
                'equipment_id' => $equipment->id,
                'generated_at' => now(),
                'status' => 'Open',
            ]));

            $result['created']++;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchingRules(Equipment $equipment): array
    {
        $rules = [];

        if ($equipment->next_maintenance_date?->isBefore(today())) {
            $rules[] = $this->recommendation(
                'overdue_maintenance',
                'Preventive maintenance is overdue',
                'The equipment maintenance date has already passed.',
                'High',
                'Schedule maintenance immediately.',
                ['next_maintenance_date' => $equipment->next_maintenance_date?->toDateString()],
            );
        } elseif ($equipment->next_maintenance_date?->betweenIncluded(today(), today()->addDays(7))) {
            $rules[] = $this->recommendation(
                'due_soon',
                'Maintenance is due soon',
                'Maintenance is due within seven days.',
                'Moderate',
                'Prepare and schedule preventive maintenance.',
                ['next_maintenance_date' => $equipment->next_maintenance_date?->toDateString()],
            );
        }

        if ($equipment->condition === 'Defective' && ! $equipment->openWorkOrders()->exists()) {
            $rules[] = $this->recommendation(
                'defective_without_work_order',
                'Defective equipment needs corrective action',
                'The equipment is marked defective and has no active work order.',
                'High',
                'Create a corrective maintenance request or work order.',
            );
        }

        $recentCompletedWorkOrders = $equipment->workOrders()
            ->completed()
            ->where('completed_at', '>=', now()->subDays(90))
            ->count();

        if ($recentCompletedWorkOrders >= 3) {
            $rules[] = $this->recommendation(
                'repeated_repairs',
                'Repeated repairs detected',
                'Multiple repair records were found within the recent period.',
                'High',
                'Conduct a comprehensive inspection.',
                ['completed_work_orders_last_90_days' => $recentCompletedWorkOrders],
            );
        }

        if (! $equipment->maintenanceSchedules()->completed()->exists() && ! $equipment->workOrders()->completed()->exists()) {
            $rules[] = $this->recommendation(
                'no_maintenance_history',
                'No maintenance history available',
                'No completed maintenance activity is recorded for this equipment.',
                'Moderate',
                'Perform an initial inspection.',
            );
        }

        if ($equipment->warranty_expiration_date?->betweenIncluded(today(), today()->addDays(30))) {
            $rules[] = $this->recommendation(
                'expiring_warranty',
                'Warranty will expire soon',
                'The warranty period will end soon.',
                'Moderate',
                'Inspect the equipment before the warranty expires.',
                ['warranty_expiration_date' => $equipment->warranty_expiration_date?->toDateString()],
            );
        }

        $beyondRepairWorkOrders = $equipment->workOrders()
            ->where('status', 'Beyond repair')
            ->get();
        $incompleteBeyondRepairWorkOrderIds = $beyondRepairWorkOrders
            ->filter(fn ($workOrder): bool => $workOrder->beyondRepairEvidenceCount() < 2)
            ->pluck('id')
            ->values()
            ->all();

        if ($equipment->condition === 'Beyond repair' || $incompleteBeyondRepairWorkOrderIds !== []) {
            $rules[] = $this->recommendation(
                'beyond_repair_evidence_incomplete',
                'Beyond-repair evidence is incomplete',
                'Required technical and photographic evidence for beyond-repair assessment is incomplete.',
                'Critical',
                'Upload at least two beyond-repair evidence photos and complete technical findings.',
                ['work_order_ids' => $incompleteBeyondRepairWorkOrderIds],
            );
        }

        $completedWithoutEvidenceIds = $equipment->workOrders()
            ->completed()
            ->get()
            ->filter(fn ($workOrder): bool => ! $workOrder->hasAfterMaintenanceEvidence())
            ->pluck('id')
            ->values()
            ->all();

        if ($completedWithoutEvidenceIds !== []) {
            $rules[] = $this->recommendation(
                'completed_without_after_evidence',
                'Completed work lacks after-maintenance evidence',
                'Completed work should have at least one after-maintenance photo.',
                'High',
                'Upload after-maintenance evidence for the completed work order.',
                ['work_order_ids' => $completedWithoutEvidenceIds],
            );
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendation(
        string $ruleKey,
        string $title,
        string $explanation,
        string $riskLevel,
        string $recommendedAction,
        array $metadata = [],
    ): array {
        return [
            'rule_key' => $ruleKey,
            'title' => $title,
            'explanation' => $explanation,
            'risk_level' => $riskLevel,
            'recommended_action' => $recommendedAction,
            'metadata' => $metadata,
        ];
    }
}
