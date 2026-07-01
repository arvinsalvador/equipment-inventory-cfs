<?php

namespace App\Http\Controllers;

use App\Services\Reports\CsvReportExporter;
use App\Services\Reports\ExcelReportExporter;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly CsvReportExporter $csvExporter,
        private readonly ExcelReportExporter $excelExporter,
    ) {}

    public function show(Request $request, string $report): View
    {
        $this->authorizeReports();

        return view('reports.show', $this->payload($request, $report));
    }

    public function print(Request $request, string $report): View
    {
        $this->authorizeReports();

        return view('reports.print', $this->payload($request, $report));
    }

    public function pdf(Request $request, string $report): View
    {
        $this->authorizeReports();

        return view('reports.pdf-ready', $this->payload($request, $report));
    }

    public function csv(Request $request, string $report): StreamedResponse
    {
        $this->authorizeReports();

        $definition = $this->reports->get($report);
        $filters = $this->filters($request, $definition['filters']);

        return $this->csvExporter->stream(
            $report.'-'.now()->format('Ymd-His').'.csv',
            $this->reports->columns($report),
            $this->reports->rows($report, $filters)
        );
    }

    public function excel(Request $request, string $report): StreamedResponse
    {
        $this->authorizeReports();

        $definition = $this->reports->get($report);
        $filters = $this->filters($request, $definition['filters']);

        return $this->excelExporter->stream(
            $report.'-'.now()->format('Ymd-His').'.xls',
            $definition['name'],
            $this->reports->columns($report),
            $this->reports->rows($report, $filters),
            $this->reports->appliedFilterLabels($report, $filters)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, string $report): array
    {
        $definition = $this->reports->get($report);
        $filters = $this->filters($request, $definition['filters']);

        return [
            'slug' => $report,
            'definition' => $definition,
            'columns' => $this->reports->columns($report),
            'rows' => $this->reports->rows($report, $filters),
            'filters' => $filters,
            'appliedFilters' => $this->reports->appliedFilterLabels($report, $filters),
            'filterOptions' => $this->reports->filterOptions(),
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $allowed): array
    {
        return collect($allowed)
            ->mapWithKeys(fn (string $filter) => [$filter => $request->query($filter)])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function authorizeReports(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }
}
