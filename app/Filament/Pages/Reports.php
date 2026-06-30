<?php

namespace App\Filament\Pages;

use App\Services\Reports\ReportRegistry;
use Filament\Pages\Page;

class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports and Analytics';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 10;

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
        return 'Reports';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function reports(): array
    {
        return app(ReportRegistry::class)->all();
    }
}
