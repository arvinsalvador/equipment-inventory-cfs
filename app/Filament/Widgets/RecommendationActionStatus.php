<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecommendation;
use Filament\Widgets\Widget;

class RecommendationActionStatus extends Widget
{
    protected string $view = 'filament.widgets.recommendation-action-status';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceRecommendation::class) ?? false;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(MaintenanceRecommendation::ACTION_STATUSES)
            ->mapWithKeys(fn (string $status): array => [
                $status => MaintenanceRecommendation::query()
                    ->where(fn ($query) => $status === 'Pending'
                        ? $query->where('action_status', 'Pending')->orWhereNull('action_status')
                        : $query->where('action_status', $status))
                    ->count(),
            ])
            ->all();
    }
}
