<?php

namespace App\Filament\Pages;

use App\Services\ExecutiveInsightService;
use Filament\Pages\Page;

class ExecutiveDecisionSupport extends Page
{
    protected string $view = 'filament.pages.executive-decision-support';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports and Analytics';

    protected static ?string $navigationLabel = 'Executive Decision Support';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('executive-dashboard.view') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Executive Decision Support';
    }

    /**
     * @return array<string, mixed>
     */
    public function insights(): array
    {
        return app(ExecutiveInsightService::class)->dashboard();
    }
}
