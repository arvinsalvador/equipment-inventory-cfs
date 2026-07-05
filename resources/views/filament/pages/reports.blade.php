<x-filament-panels::page>
    @php
        $reports = $this->reports();
        $selectedSlug = $this->selectedReportSlug();
        $selectedReport = $this->selectedReport();
        $filterOptions = $this->filterOptions();
        $filters = $this->filters();
        $appliedFilters = $this->appliedFilters();
        $allColumns = $this->allColumns();
        $selectedColumns = $this->selectedColumns();
        $selectedColumnKeys = $this->selectedColumnKeys();
        $rows = $this->rows();
        $generatedAt = now();
        $showUrl = route('reports.show', $this->routeParameters());
        $printUrl = route('reports.print', $this->routeParameters('print'));
        $pdfUrl = route('reports.pdf', $this->routeParameters('pdf'));
        $csvUrl = route('reports.csv', $this->routeParameters('csv'));
        $excelUrl = route('reports.excel', $this->routeParameters('excel'));
        $columnKeys = collect($allColumns)->pluck('key')->values();
        $statusClass = function (mixed $value): string {
            $value = str($value ?? '')->lower()->toString();

            return match (true) {
                str_contains($value, 'critical'), str_contains($value, 'overdue'), str_contains($value, 'beyond repair') => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300',
                str_contains($value, 'approved'), str_contains($value, 'completed'), str_contains($value, 'good'), str_contains($value, 'available') => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300',
                str_contains($value, 'pending'), str_contains($value, 'submitted'), str_contains($value, 'due soon'), str_contains($value, 'high') => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300',
                default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300',
            };
        };
        $highlightRow = function (array $row): string {
            $haystack = str(implode(' ', array_map(fn ($value) => (string) $value, $row)))->lower()->toString();

            return str_contains($haystack, 'critical') || str_contains($haystack, 'overdue') || str_contains($haystack, 'beyond repair')
                ? 'bg-warning-50/70 dark:bg-warning-400/5'
                : '';
        };
    @endphp

    <div
        x-data="{
            selectedColumns: @js($selectedColumnKeys),
            defaultColumns: @js($columnKeys),
            selectAll() { this.selectedColumns = [...this.defaultColumns] },
            clearAll() { this.selectedColumns = [] },
            restoreDefault() { this.selectedColumns = [...this.defaultColumns] },
            printPdf() {
                const frame = this.$refs.pdfPreview;
                if (frame && frame.contentWindow) {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                }
            },
        }"
        class="space-y-6"
    >
        <x-filament::section>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-primary-600 dark:text-primary-400">Report Center</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">Professional Report Builder</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Generate professional inventory, maintenance, executive, and analytical reports.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button :href="$showUrl" tag="a" icon="heroicon-o-eye">
                        Preview
                    </x-filament::button>
                    <x-filament::button :href="$printUrl" tag="a" color="gray" icon="heroicon-o-printer">
                        Print
                    </x-filament::button>
                    <x-filament::button :href="$pdfUrl" tag="a" color="warning" icon="heroicon-o-document-text">
                        Export PDF
                    </x-filament::button>
                    <x-filament::button :href="$excelUrl" tag="a" color="success" icon="heroicon-o-table-cells">
                        Export Excel
                    </x-filament::button>
                    <x-filament::button :href="$csvUrl" tag="a" color="gray" icon="heroicon-o-arrow-down-tray">
                        Export CSV
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <form method="GET" action="{{ url('/admin/reports') }}" class="space-y-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div class="space-y-6 xl:col-span-3">
                    <x-filament::section>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Report Type</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose the report definition and the builder will adapt the available filters and fields.</p>
                            </div>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Report type</span>
                                <select
                                    name="report"
                                    class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                    onchange="this.form.submit()"
                                >
                                    @foreach ($reports as $slug => $report)
                                        <option value="{{ $slug }}" @selected($selectedSlug === $slug)>{{ $report['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                {{ $selectedReport['description'] }}
                            </p>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="space-y-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Filters</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Refine the data set before previewing or exporting.</p>
                                </div>
                                <div class="flex gap-2">
                                    <x-filament::button type="submit" size="sm" icon="heroicon-o-funnel">
                                        Apply Filters
                                    </x-filament::button>
                                    <x-filament::button :href="url('/admin/reports?report='.$selectedSlug)" tag="a" size="sm" color="gray">
                                        Reset Filters
                                    </x-filament::button>
                                </div>
                            </div>

                            @if ($selectedReport['filters'] === [])
                                <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                                    This report does not require filters.
                                </div>
                            @else
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    @foreach ($selectedReport['filters'] as $filter)
                                        <label class="block">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ str($filter)->replace('_', ' ')->title() }}</span>
                                            @if (in_array($filter, ['date_from', 'date_to'], true))
                                                <input
                                                    type="date"
                                                    name="{{ $filter }}"
                                                    value="{{ $filters[$filter] ?? '' }}"
                                                    class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                                >
                                            @else
                                                <select
                                                    name="{{ $filter }}"
                                                    class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                                >
                                                    <option value="">All</option>
                                                    @foreach (($filterOptions[$filter] ?? []) as $value => $label)
                                                        <option value="{{ $value }}" @selected(($filters[$filter] ?? '') == $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="space-y-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Columns to Include</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose which fields appear in preview, print, PDF, CSV, and Excel outputs.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button type="button" size="sm" color="gray" x-on:click="selectAll()">Select All</x-filament::button>
                                    <x-filament::button type="button" size="sm" color="gray" x-on:click="clearAll()">Clear All</x-filament::button>
                                    <x-filament::button type="button" size="sm" color="gray" x-on:click="restoreDefault()">Restore Default</x-filament::button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($allColumns as $column)
                                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5">
                                        <input
                                            type="checkbox"
                                            name="columns[]"
                                            value="{{ $column['key'] }}"
                                            x-model="selectedColumns"
                                            class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >
                                        <span class="text-gray-700 dark:text-gray-200">{{ $column['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="xl:col-span-4">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Sorting & Grouping</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Prepared report organization controls. Existing report logic remains unchanged.</p>
                            </div>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Sort By</span>
                                <select name="sort_by" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                    <option value="">Default</option>
                                    @foreach ($allColumns as $column)
                                        <option value="{{ $column['key'] }}" @selected(request('sort_by') === $column['key'])>{{ $column['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Direction</span>
                                <select name="direction" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                    <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
                                    <option value="desc" @selected(request('direction') === 'desc')>Descending</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Group By</span>
                                <select name="group_by" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                    @foreach (['none' => 'None', 'category' => 'Category', 'location' => 'Location', 'technician' => 'Technician', 'maintenance_type' => 'Maintenance Type', 'status' => 'Status', 'year' => 'Year'] as $value => $label)
                                        <option value="{{ $value }}" @selected(request('group_by', 'none') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </x-filament::section>
                </div>

                <div class="space-y-6">
                    <x-filament::section>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Output Options</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Control document presentation for generated outputs.</p>
                            </div>

                            <div class="space-y-3">
                                @foreach ([
                                    'include_logo' => 'Include University Logo',
                                    'include_title' => 'Include Report Title',
                                    'include_filters' => 'Include Applied Filters',
                                    'include_generated_date' => 'Include Generated Date',
                                    'include_generated_by' => 'Include Generated By',
                                    'include_page_numbers' => 'Include Page Numbers',
                                    'fit_to_width' => 'Fit to Width',
                                ] as $name => $label)
                                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                                        <input type="checkbox" name="{{ $name }}" value="1" checked class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <fieldset class="space-y-2">
                                <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Orientation</legend>
                                <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="radio" name="orientation" value="landscape" checked class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span>Landscape Orientation</span>
                                </label>
                                <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="radio" name="orientation" value="portrait" class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span>Portrait Orientation</span>
                                </label>
                            </fieldset>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="space-y-3">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Generate Report</h3>
                            <x-filament::button type="submit" class="w-full justify-center" icon="heroicon-o-play">
                                Generate Report
                            </x-filament::button>
                            <x-filament::button :href="$showUrl" tag="a" color="gray" class="w-full justify-center" icon="heroicon-o-eye">
                                Preview
                            </x-filament::button>
                            <x-filament::button :href="$printUrl" tag="a" color="gray" class="w-full justify-center" icon="heroicon-o-printer">
                                Print
                            </x-filament::button>
                            <x-filament::button :href="$pdfUrl" tag="a" color="warning" class="w-full justify-center" icon="heroicon-o-document-text">
                                Export PDF
                            </x-filament::button>
                            <x-filament::button :href="$excelUrl" tag="a" color="success" class="w-full justify-center" icon="heroicon-o-table-cells">
                                Export Excel
                            </x-filament::button>
                            <x-filament::button :href="$csvUrl" tag="a" color="gray" class="w-full justify-center" icon="heroicon-o-arrow-down-tray">
                                Export CSV
                            </x-filament::button>
                            <x-filament::button :href="url('/admin/reports')" tag="a" color="gray" class="w-full justify-center">
                                Reset
                            </x-filament::button>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="space-y-3">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recent Exports</h3>
                            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                                Session-only recent reports will appear here after export actions.
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            </div>
        </form>

        <x-filament::section>
            <div class="space-y-5">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Report Summary</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Generated result context for the current builder selections.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Matching Records</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $rows->count() }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Report Type</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $selectedReport['name'] }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Generated Date</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $generatedAt->format('Y-m-d') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Generated Time</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $generatedAt->format('H:i') }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Generated By</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ auth()->user()?->name ?? 'System' }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Output Format</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">Preview / Print / PDF / Excel / CSV</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 md:col-span-2">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Applied Filters</p>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                            @forelse ($appliedFilters as $label => $value)
                                <span class="mr-2 inline-flex rounded-md bg-white px-2 py-1 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">{{ $label }}: {{ $value }}</span>
                            @empty
                                None
                            @endforelse
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Preview</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Interactive preview using the selected columns and filters.</p>
                    </div>
                    <x-filament::button :href="$showUrl" tag="a" size="sm" color="gray" icon="heroicon-o-arrows-pointing-out">
                        Open Full Preview
                    </x-filament::button>
                </div>

                @if ($rows->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/10">
                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">No records matched the selected filters.</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Adjust the filters or reset the builder to broaden the report.</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    @foreach ($selectedColumns as $column)
                                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                            {{ $column['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-950">
                                @foreach ($rows->take(25) as $row)
                                    <tr class="{{ $loop->even ? 'bg-gray-50/60 dark:bg-white/[0.02]' : '' }} {{ $highlightRow($row) }}">
                                        @foreach ($selectedColumns as $column)
                                            @php
                                                $value = $row[$column['key']] ?? '';
                                                $isNumeric = is_numeric($value);
                                                $isStatus = str($column['key'])->contains(['status', 'condition', 'priority', 'risk', 'recommendation', 'grade']);
                                            @endphp
                                            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-200 {{ $isNumeric ? 'text-right tabular-nums' : '' }}">
                                                @if ($isStatus && filled($value))
                                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClass($value) }}">{{ $value }}</span>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($rows->count() > 25)
                        <p class="text-sm text-gray-600 dark:text-gray-400">Showing the first 25 records in-page. Exports include all {{ $rows->count() }} matching records.</p>
                    @endif
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="space-y-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">PDF Preview</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Generate a report to preview the PDF-ready layout directly inside the Report Center.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button :href="$pdfUrl" tag="a" target="_blank" size="sm" icon="heroicon-o-arrow-top-right-on-square">
                            Open PDF
                        </x-filament::button>
                        <x-filament::button :href="$pdfUrl" tag="a" size="sm" color="warning" icon="heroicon-o-arrow-down-tray">
                            Download PDF
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-printer" x-on:click="printPdf()">
                            Print PDF
                        </x-filament::button>
                        <x-filament::button :href="url()->full()" tag="a" size="sm" color="gray" icon="heroicon-o-arrow-path">
                            Refresh Preview
                        </x-filament::button>
                    </div>
                </div>

                @if ($rows->isEmpty())
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/10">
                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">No records matched the selected filters.</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">The PDF preview will display once the report has matching records.</p>
                    </div>
                @else
                    <iframe
                        x-ref="pdfPreview"
                        title="PDF Preview"
                        src="{{ $pdfUrl }}"
                        class="h-[760px] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
                    ></iframe>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
