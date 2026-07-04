<?php

namespace App\Services;

use App\Models\AssetActionRequest;
use App\Models\BudgetPlan;
use App\Models\Equipment;
use Illuminate\Support\Collection;

class BudgetForecastingService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function generateForecastItemsForYear(int $year): Collection
    {
        return $this->suggestBudgetItems($year);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function suggestBudgetItems(?int $year = null): Collection
    {
        $year ??= now()->year;
        $items = collect();

        AssetActionRequest::query()
            ->with('equipment.lifecycleProfile')
            ->approved()
            ->get()
            ->each(function (AssetActionRequest $request) use ($items, $year): void {
                $items->push($this->fromAssetActionRequest($request, $year));
            });

        Equipment::query()
            ->with(['lifecycleProfile', 'workOrders'])
            ->where(function ($query): void {
                $query
                    ->where('condition', 'Beyond repair')
                    ->orWhereHas('lifecycleProfile', function ($query): void {
                        $query
                            ->whereIn('lifecycle_status', ['Replacement Candidate', 'Beyond Repair', 'High Maintenance'])
                            ->orWhereIn('replacement_recommendation', ['Replace Equipment', 'Dispose Equipment', 'Schedule Major Inspection', 'Repair'])
                            ->orWhere('health_grade', 'Critical')
                            ->orWhere('health_score', '<', 40);
                    });
            })
            ->get()
            ->each(function (Equipment $equipment) use ($items, $year): void {
                $items->push($this->fromEquipment($equipment, $year));
            });

        return $items
            ->unique(fn (array $item): string => ($item['asset_action_request_id'] ? 'aar-'.$item['asset_action_request_id'] : 'eq-'.$item['equipment_id'].'-'.$item['item_type']))
            ->values();
    }

    public function calculateEstimatedReplacementCost(Equipment $equipment, ?AssetActionRequest $assetActionRequest = null): float
    {
        if ($assetActionRequest && $assetActionRequest->estimated_cost !== null) {
            return (float) $assetActionRequest->estimated_cost;
        }

        $metadata = $equipment->lifecycleProfile?->metadata ?? [];
        foreach (['estimated_replacement_cost', 'replacement_cost', 'estimated_cost'] as $key) {
            if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                return (float) $metadata[$key];
            }
        }

        if ($equipment->acquisition_cost !== null) {
            return (float) $equipment->acquisition_cost;
        }

        return 0.0;
    }

    public function calculatePriority(Equipment $equipment): string
    {
        $profile = $equipment->lifecycleProfile;

        if ($equipment->condition === 'Beyond repair' || $profile?->lifecycle_status === 'Beyond Repair' || $profile?->isCritical()) {
            return 'Critical';
        }

        if ($profile?->lifecycle_status === 'Replacement Candidate' || $profile?->replacement_recommendation === 'Replace Equipment') {
            return 'High';
        }

        if ($profile?->lifecycle_status === 'High Maintenance' || $profile?->replacement_recommendation === 'Schedule Major Inspection') {
            return 'High';
        }

        return 'Normal';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeForecast(int $year): array
    {
        $items = $this->generateForecastItemsForYear($year);

        return [
            'fiscal_year' => $year,
            'item_count' => $items->count(),
            'estimated_total' => $items->sum('estimated_cost'),
            'critical_count' => $items->where('priority', 'Critical')->count(),
            'replacement_count' => $items->where('item_type', 'Replacement')->count(),
        ];
    }

    /**
     * @return array{added: int, skipped: int, total_candidates: int}
     */
    public function addForecastItemsToPlan(BudgetPlan $plan): array
    {
        $added = 0;
        $skipped = 0;
        $items = $this->generateForecastItemsForYear($plan->fiscal_year);

        foreach ($items as $item) {
            if ($this->planAlreadyHasItem($plan, $item)) {
                $skipped++;

                continue;
            }

            $plan->items()->create($item);
            $added++;
        }

        if ($added > 0) {
            $plan->recalculateTotal();
        }

        app(AuditLogService::class)->log('forecast_generated', 'Budget Plan', "Forecast generated for budget plan {$plan->plan_number}.", auth()->user(), $plan, null, null, [
            'added' => $added,
            'skipped' => $skipped,
            'total_candidates' => $items->count(),
        ]);

        return [
            'added' => $added,
            'skipped' => $skipped,
            'total_candidates' => $items->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromAssetActionRequest(AssetActionRequest $request, int $year): array
    {
        $equipment = $request->equipment;
        $cost = $equipment ? $this->calculateEstimatedReplacementCost($equipment, $request) : (float) ($request->estimated_cost ?? 0);
        $note = $cost > 0 ? null : 'No asset action, lifecycle, or acquisition cost available.';

        return [
            'equipment_id' => $request->equipment_id,
            'asset_action_request_id' => $request->id,
            'item_type' => $this->mapRequestType($request->request_type),
            'description' => "{$request->request_type} request {$request->request_number}",
            'priority' => $request->priority,
            'estimated_cost' => $cost,
            'justification' => $request->justification,
            'forecast_reason' => trim('Approved asset action request. '.($note ?? '')),
            'target_period' => (string) $year,
            'status' => 'Proposed',
            'metadata' => [
                'source' => 'asset_action_request',
                'request_number' => $request->request_number,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromEquipment(Equipment $equipment, int $year): array
    {
        $profile = $equipment->lifecycleProfile;
        $cost = $this->calculateEstimatedReplacementCost($equipment);
        $itemType = $this->equipmentItemType($equipment);
        $costNote = $cost > 0 ? null : 'No lifecycle or acquisition cost available; estimate set to 0.';

        return [
            'equipment_id' => $equipment->id,
            'asset_action_request_id' => null,
            'item_type' => $itemType,
            'description' => "{$itemType} forecast for {$equipment->equipment_code} - {$equipment->equipment_name}",
            'priority' => $this->calculatePriority($equipment),
            'estimated_cost' => $cost,
            'justification' => $profile?->replacement_reason,
            'forecast_reason' => collect([
                $profile?->lifecycle_status,
                $profile?->replacement_recommendation,
                $equipment->condition === 'Beyond repair' ? 'Beyond repair equipment condition' : null,
                $costNote,
            ])->filter()->implode('; '),
            'target_period' => (string) $year,
            'status' => 'Proposed',
            'metadata' => [
                'source' => 'lifecycle_forecast',
                'health_score' => $profile?->health_score,
                'health_grade' => $profile?->health_grade,
            ],
        ];
    }

    private function mapRequestType(string $requestType): string
    {
        return $requestType === 'Disposal' ? 'Disposal Support' : $requestType;
    }

    private function equipmentItemType(Equipment $equipment): string
    {
        $profile = $equipment->lifecycleProfile;

        if ($equipment->condition === 'Beyond repair' || $profile?->lifecycle_status === 'Beyond Repair' || $profile?->replacement_recommendation === 'Dispose Equipment') {
            return 'Disposal Support';
        }

        if ($profile?->lifecycle_status === 'High Maintenance' || in_array($profile?->replacement_recommendation, ['Repair', 'Schedule Major Inspection'], true)) {
            return $profile?->replacement_recommendation === 'Schedule Major Inspection' ? 'Inspection' : 'Major Repair';
        }

        return 'Replacement';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function planAlreadyHasItem(BudgetPlan $plan, array $item): bool
    {
        return $plan->items()
            ->when($item['asset_action_request_id'] ?? null, fn ($query, $id) => $query->where('asset_action_request_id', $id))
            ->when(! ($item['asset_action_request_id'] ?? null), fn ($query) => $query
                ->where('equipment_id', $item['equipment_id'])
                ->where('item_type', $item['item_type']))
            ->exists();
    }
}
