<?php

namespace App\Filament\Widgets;

use App\Models\Equipment;
use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class EquipmentHealth extends Widget
{
    protected string $view = 'filament.widgets.equipment-health';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function rows(): array
    {
        $total = max(Equipment::query()->where('is_archived', false)->count(), 1);

        $critical = MaintenanceRecommendation::query()
            ->where('status', 'Open')
            ->where('risk_level', 'Critical')
            ->distinct('equipment_id')
            ->count('equipment_id');

        $preventive = MaintenanceRecommendation::query()
            ->where('status', 'Open')
            ->whereIn('rule_key', ['overdue_maintenance', 'due_soon', 'no_maintenance_history', 'expiring_warranty'])
            ->distinct('equipment_id')
            ->count('equipment_id');

        $corrective = MaintenanceRecommendation::query()
            ->where('status', 'Open')
            ->whereIn('rule_key', ['defective_without_work_order', 'repeated_repairs', 'completed_without_after_evidence'])
            ->distinct('equipment_id')
            ->count('equipment_id');

        $atRisk = min($total, max($critical, $preventive, $corrective));
        $healthy = max(0, $total - $atRisk);

        return [
            $this->row('Healthy', $healthy, $total, 'green'),
            $this->row('Needs Preventive Maintenance', $preventive, $total, 'yellow'),
            $this->row('Needs Corrective Maintenance', $corrective, $total, 'orange'),
            $this->row('Critical', $critical, $total, 'red'),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function row(string $label, int $count, int $total, string $color): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'percentage' => (int) round(($count / $total) * 100),
            'color' => $color,
        ];
    }
}
