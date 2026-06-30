<?php

namespace App\Filament\Pages;

use App\Services\MaintenanceAnalyticsService;
use Filament\Pages\Page;

class MaintenanceAnalytics extends Page
{
    protected string $view = 'filament.pages.maintenance-analytics';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports and Analytics';

    protected static ?string $navigationLabel = 'Maintenance Analytics';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Maintenance Analytics';
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return app(MaintenanceAnalyticsService::class)->dashboard(request()->query('period', 'this_month'));
    }
}
