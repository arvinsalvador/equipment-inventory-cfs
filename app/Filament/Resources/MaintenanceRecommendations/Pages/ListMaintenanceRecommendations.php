<?php

namespace App\Filament\Resources\MaintenanceRecommendations\Pages;

use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;
use App\Models\MaintenanceRecommendation;
use App\Services\AuditLogService;
use App\Services\MaintenanceRecommendationEngine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListMaintenanceRecommendations extends ListRecords
{
    protected static string $resource = MaintenanceRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshRecommendations')
                ->label('Refresh Recommendations')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Refresh recommendations')
                ->modalDescription('Scan eligible equipment and update rule-based recommendations. This may take time on large inventories.')
                ->visible(fn (): bool => auth()->user()?->can('refresh', MaintenanceRecommendation::class) ?? false)
                ->action(function (): void {
                    try {
                        $summary = app(MaintenanceRecommendationEngine::class)->generateForAllEquipment();

                        app(AuditLogService::class)->log('recommendations_refreshed', 'AI Recommendation', 'Recommendation refresh completed.', auth()->user(), null, null, null, [
                            'scan_type' => 'full',
                            ...$summary,
                        ]);

                        $notification = Notification::make()
                            ->title('Recommendation refresh completed')
                            ->body(self::summaryBody($summary));

                        $summary['errors'] > 0
                            ? $notification->warning()
                            : $notification->success();

                        $notification->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Recommendation refresh failed')
                            ->body('The refresh could not be completed. Check the application logs for details.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @param  array{equipment_checked: int, created: int, updated: int, unchanged: int, skipped: int, errors: int}  $summary
     */
    private static function summaryBody(array $summary): string
    {
        return implode("\n", [
            'Equipment scanned: '.$summary['equipment_checked'],
            'New recommendations: '.$summary['created'],
            'Existing recommendations updated: '.$summary['updated'],
            'Unchanged recommendations: '.$summary['unchanged'],
            'Skipped equipment: '.$summary['skipped'],
            'Errors: '.$summary['errors'],
        ]);
    }
}
