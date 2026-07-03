<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentLifecycleProfile;

class EquipmentLifecycleAnalyzer
{
    public function analyze(Equipment $equipment): EquipmentLifecycleProfile
    {
        $equipment->loadMissing(['category', 'lifecycleProfile']);

        $scoreDetails = $this->calculateHealthScoreDetails($equipment);
        $score = $scoreDetails['score'];
        $life = $this->estimateRemainingLife($equipment);
        $status = $this->determineLifecycleStatus($equipment, $score);
        $recommendation = $this->determineReplacementRecommendation($equipment, $score);

        return EquipmentLifecycleProfile::updateOrCreate(
            ['equipment_id' => $equipment->id],
            [
                'expected_useful_life_years' => $life['expected_useful_life_years'],
                'estimated_end_of_life_date' => $life['estimated_end_of_life_date'],
                'estimated_remaining_life_months' => $life['estimated_remaining_life_months'],
                'health_score' => $score,
                'health_grade' => $this->determineHealthGrade($score),
                'lifecycle_status' => $status,
                'replacement_recommendation' => $recommendation['recommendation'],
                'replacement_reason' => $recommendation['reason'],
                'last_calculated_at' => now(),
                'metadata' => [
                    'score_details' => $scoreDetails['details'],
                    'repair_frequency' => $this->summarizeRepairFrequency($equipment),
                    'maintenance_cost' => $this->summarizeMaintenanceCost($equipment),
                    'useful_life' => $life,
                ],
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    public function analyzeAll(): array
    {
        $summary = [
            'equipment_analyzed' => 0,
            'replacement_candidates' => 0,
            'critical_equipment' => 0,
            'high_maintenance_equipment' => 0,
        ];

        Equipment::query()->with('category')->each(function (Equipment $equipment) use (&$summary): void {
            $profile = $this->analyze($equipment);
            $summary['equipment_analyzed']++;
            $summary['replacement_candidates'] += $profile->isReplacementCandidate() ? 1 : 0;
            $summary['critical_equipment'] += $profile->isCritical() ? 1 : 0;
            $summary['high_maintenance_equipment'] += $profile->lifecycle_status === 'High Maintenance' ? 1 : 0;
        });

        return $summary;
    }

    public function calculateHealthScore(Equipment $equipment): int
    {
        return $this->calculateHealthScoreDetails($equipment)['score'];
    }

    public function determineHealthGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Poor',
            default => 'Critical',
        };
    }

    public function determineLifecycleStatus(Equipment $equipment, int $score): string
    {
        $repairFrequency = $this->summarizeRepairFrequency($equipment);
        $ageRatio = $this->ageRatio($equipment);

        if ($equipment->operational_status === 'Retired' || $equipment->operational_status === 'Disposed') {
            return 'Retired';
        }

        if ($equipment->condition === 'Beyond repair') {
            return 'Beyond Repair';
        }

        if ($score < 40) {
            return 'Replacement Candidate';
        }

        if ($repairFrequency['completed_90_days'] >= 3) {
            return 'High Maintenance';
        }

        if ($ageRatio !== null && $ageRatio > 0.75) {
            return 'Aging';
        }

        if ($equipment->acquisition_date?->greaterThanOrEqualTo(today()->subYear())) {
            return 'New';
        }

        return 'Active';
    }

    /**
     * @return array{recommendation: string, reason: string}
     */
    public function determineReplacementRecommendation(Equipment $equipment, int $score): array
    {
        $repairFrequency = $this->summarizeRepairFrequency($equipment);
        $cost = $this->summarizeMaintenanceCost($equipment);

        if ($equipment->condition === 'Beyond repair' || $score < 30) {
            return ['recommendation' => 'Dispose Equipment', 'reason' => 'Equipment is beyond repair or has a critical health score.'];
        }

        if ($score < 40) {
            return ['recommendation' => 'Replace Equipment', 'reason' => 'Health score is below the replacement threshold.'];
        }

        if ($repairFrequency['completed_90_days'] >= 3 && $cost['total_cost'] >= 1000) {
            return ['recommendation' => 'Schedule Major Inspection', 'reason' => 'Repeated recent repairs and high maintenance cost require inspection.'];
        }

        if ($equipment->condition === 'Defective') {
            return ['recommendation' => 'Repair', 'reason' => 'Equipment is defective but not yet beyond repair.'];
        }

        if ($score < 75) {
            return ['recommendation' => 'Continue Monitoring', 'reason' => 'Health score is fair and should be monitored.'];
        }

        return ['recommendation' => 'Continue Maintenance', 'reason' => 'Equipment health remains acceptable for normal maintenance.'];
    }

    /**
     * @return array{expected_useful_life_years: int, estimated_end_of_life_date: string|null, estimated_remaining_life_months: int|null, note?: string}
     */
    public function estimateRemainingLife(Equipment $equipment): array
    {
        $years = $equipment->lifecycleProfile?->expected_useful_life_years
            ?: $this->defaultUsefulLifeYears($equipment);

        if (! $equipment->acquisition_date) {
            return [
                'expected_useful_life_years' => $years,
                'estimated_end_of_life_date' => null,
                'estimated_remaining_life_months' => null,
                'note' => 'Acquisition date is missing.',
            ];
        }

        $endOfLife = $equipment->acquisition_date->copy()->addYears($years);

        return [
            'expected_useful_life_years' => $years,
            'estimated_end_of_life_date' => $endOfLife->toDateString(),
            'estimated_remaining_life_months' => max(0, (int) today()->diffInMonths($endOfLife, false)),
        ];
    }

    /**
     * @return array{completed_90_days: int, completed_180_days: int, total_completed: int}
     */
    public function summarizeRepairFrequency(Equipment $equipment): array
    {
        return [
            'completed_90_days' => $equipment->workOrders()->completed()->where('completed_at', '>=', now()->subDays(90))->count(),
            'completed_180_days' => $equipment->workOrders()->completed()->where('completed_at', '>=', now()->subDays(180))->count(),
            'total_completed' => $equipment->workOrders()->completed()->count(),
        ];
    }

    /**
     * @return array{labor_cost: float, parts_cost: float, external_service_cost: float, total_cost: float}
     */
    public function summarizeMaintenanceCost(Equipment $equipment): array
    {
        return [
            'labor_cost' => (float) $equipment->workOrders()->sum('labor_cost'),
            'parts_cost' => (float) $equipment->workOrders()->sum('parts_cost'),
            'external_service_cost' => (float) $equipment->workOrders()->sum('external_service_cost'),
            'total_cost' => (float) $equipment->workOrders()->sum('total_cost'),
        ];
    }

    /**
     * @return array{score: int, details: array<int, array<string, mixed>>}
     */
    private function calculateHealthScoreDetails(Equipment $equipment): array
    {
        $score = 100;
        $details = [];

        $this->subtract($score, $details, 'Equipment condition', [
            'New' => 0, 'Good' => 5, 'Fair' => 15, 'Needs inspection' => 25, 'Needs maintenance' => 30, 'Defective' => 50, 'Beyond repair' => 80,
        ][$equipment->condition] ?? 0, $equipment->condition);

        $this->subtract($score, $details, 'Operational status', [
            'Available' => 0, 'In use' => 0, 'Under inspection' => 10, 'Under maintenance' => 15, 'Unavailable' => 40, 'Retired' => 80, 'Disposed' => 90, 'Transferred' => 5,
        ][$equipment->operational_status] ?? 0, $equipment->operational_status);

        if ($equipment->maintenanceSchedules()->overdue()->exists()) {
            $this->subtract($score, $details, 'Maintenance status', 20, 'Overdue maintenance');
        }

        if (! $equipment->completedWorkOrders()->exists() && ! $equipment->maintenanceSchedules()->completed()->exists()) {
            $this->subtract($score, $details, 'Maintenance history', 10, 'No maintenance history');
        }

        $frequency = $this->summarizeRepairFrequency($equipment);
        if ($frequency['completed_180_days'] >= 5) {
            $this->subtract($score, $details, 'Repair frequency', 30, '5 or more completed work orders in 180 days');
        } elseif ($frequency['completed_90_days'] >= 3) {
            $this->subtract($score, $details, 'Repair frequency', 20, '3 or more completed work orders in 90 days');
        }

        if ($equipment->maintenanceRecommendations()->where('status', 'Open')->where('risk_level', 'Critical')->exists()) {
            $this->subtract($score, $details, 'Open recommendation', 20, 'Critical open recommendation');
        } elseif ($equipment->maintenanceRecommendations()->where('status', 'Open')->where('risk_level', 'High')->exists()) {
            $this->subtract($score, $details, 'Open recommendation', 10, 'High open recommendation');
        }

        $ageRatio = $this->ageRatio($equipment);
        if ($ageRatio !== null) {
            if ($ageRatio > 1) {
                $this->subtract($score, $details, 'Age factor', 30, 'More than expected useful life');
            } elseif ($ageRatio > 0.75) {
                $this->subtract($score, $details, 'Age factor', 15, 'More than 75% of expected useful life');
            } elseif ($ageRatio > 0.5) {
                $this->subtract($score, $details, 'Age factor', 5, 'More than 50% of expected useful life');
            }
        }

        return ['score' => max(0, min(100, $score)), 'details' => $details];
    }

    private function subtract(int &$score, array &$details, string $factor, int $points, string $reason): void
    {
        if ($points <= 0) {
            return;
        }

        $score -= $points;
        $details[] = compact('factor', 'points', 'reason');
    }

    private function defaultUsefulLifeYears(Equipment $equipment): int
    {
        $category = strtolower((string) $equipment->category?->name);

        return match (true) {
            str_contains($category, 'computer') => 5,
            str_contains($category, 'air-conditioning') => 8,
            str_contains($category, 'office') => 7,
            str_contains($category, 'laboratory') => 10,
            str_contains($category, 'agricultural') => 10,
            str_contains($category, 'weather monitoring') => 8,
            str_contains($category, 'water') => 10,
            default => 7,
        };
    }

    private function ageRatio(Equipment $equipment): ?float
    {
        if (! $equipment->acquisition_date) {
            return null;
        }

        $years = $equipment->lifecycleProfile?->expected_useful_life_years
            ?: $this->defaultUsefulLifeYears($equipment);

        return $equipment->acquisition_date->diffInDays(today()) / max(1, $years * 365);
    }
}
