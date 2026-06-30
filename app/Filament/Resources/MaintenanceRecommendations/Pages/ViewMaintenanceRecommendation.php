<?php

namespace App\Filament\Resources\MaintenanceRecommendations\Pages;

use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceRecommendation extends ViewRecord
{
    protected static string $resource = MaintenanceRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaintenanceRecommendationResource::markReviewedAction(),
            MaintenanceRecommendationResource::markResolvedAction(),
            MaintenanceRecommendationResource::dismissAction(),
            MaintenanceRecommendationResource::approveAction(),
            MaintenanceRecommendationResource::rejectAction(),
            MaintenanceRecommendationResource::executeAction(),
            MaintenanceRecommendationResource::cancelAction(),
        ];
    }
}
