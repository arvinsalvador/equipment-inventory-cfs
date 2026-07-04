<?php

namespace App\Filament\Pages;

use App\Services\ProductionReadinessService;
use Filament\Pages\Page;

class ProductionReadiness extends Page
{
    protected string $view = 'filament.pages.production-readiness';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $navigationLabel = 'Production Readiness';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('production-readiness.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Production Readiness';
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $service = app(ProductionReadinessService::class);

        return [
            'score' => $service->getOverallReadinessScore(),
            'security' => $service->getSecurityChecklist(),
            'performance' => $service->getPerformanceChecklist(),
            'deployment' => $service->getDeploymentChecklist(),
            'backup' => $service->getBackupChecklist(),
            'queue' => $service->getQueueChecklist(),
            'storage' => $service->getStorageChecklist(),
            'pwa' => $service->getPwaChecklist(),
            'android' => $service->getAndroidReadinessChecklist(),
            'androidPackaging' => $service->getAndroidPackagingChecklist(),
            'androidRelease' => $service->getAndroidReleaseChecklist(),
            'warnings' => $service->getProductionWarnings(),
            'actions' => $service->getRecommendedActions(),
        ];
    }
}
