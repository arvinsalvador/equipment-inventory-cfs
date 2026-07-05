<?php

namespace App\Filament\Pages;

use App\Services\Reports\ReportRegistry;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

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
        return 'Report Center';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function reports(): array
    {
        return app(ReportRegistry::class)->all();
    }

    public function selectedReportSlug(): string
    {
        $reports = $this->reports();
        $requested = request()->query('report');

        if (is_string($requested) && array_key_exists($requested, $reports)) {
            return $requested;
        }

        return array_key_first($reports);
    }

    /**
     * @return array<string, mixed>
     */
    public function selectedReport(): array
    {
        return app(ReportRegistry::class)->get($this->selectedReportSlug());
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function allColumns(): array
    {
        return app(ReportRegistry::class)->columns($this->selectedReportSlug(), false);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function selectedColumns(): array
    {
        return app(ReportRegistry::class)->columns($this->selectedReportSlug());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return app(ReportRegistry::class)->rows($this->selectedReportSlug(), $this->filters());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return collect($this->selectedReport()['filters'])
            ->mapWithKeys(fn (string $filter) => [$filter => request()->query($filter)])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function appliedFilters(): array
    {
        return app(ReportRegistry::class)->appliedFilterLabels($this->selectedReportSlug(), $this->filters());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function filterOptions(): array
    {
        return app(ReportRegistry::class)->filterOptions();
    }

    /**
     * @return array<int, string>
     */
    public function selectedColumnKeys(): array
    {
        $selected = request()->query('columns', []);

        if (! is_array($selected) || $selected === []) {
            return collect($this->allColumns())->pluck('key')->all();
        }

        return array_values(array_filter($selected, 'is_string'));
    }

    /**
     * @return array<string, mixed>
     */
    public function routeParameters(string $format = 'preview'): array
    {
        $params = array_merge(
            ['report' => $this->selectedReportSlug()],
            $this->filters(),
            ['columns' => $this->selectedColumnKeys()]
        );

        if ($format !== 'preview') {
            $params['output'] = $format;
        }

        return $params;
    }
}
