<x-filament-panels::page>
    @php
        $analytics = $this->analytics();
        $badgeClasses = [
            'green' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-950 dark:text-green-300',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300',
            'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950 dark:text-red-300',
            'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950 dark:text-blue-300',
            'gray' => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-900 dark:text-gray-300',
        ];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Reports and Analytics</p>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">Maintenance Analytics Dashboard</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Displays maintenance performance, equipment health, work order activity, AI recommendation trends, and location-based insights using existing system records.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-400">
                        <span class="rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">Date generated: {{ $analytics['generated_at'] }}</span>
                        <span class="rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">Current month: {{ $analytics['current_month'] }}</span>
                        <span class="rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">Period: {{ $analytics['period_label'] }} ({{ $analytics['range_label'] }})</span>
                    </div>
                </div>

                <form method="GET" action="{{ url('/admin/maintenance-analytics') }}" class="flex min-w-64 flex-col gap-2">
                    <label for="period" class="text-xs font-semibold uppercase text-gray-500">Period filter</label>
                    <div class="flex gap-2">
                        <select id="period" name="period" class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            @foreach ($analytics['periods'] as $value => $label)
                                <option value="{{ $value }}" @selected($analytics['period'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-filament::button type="submit" size="sm">Apply</x-filament::button>
                    </div>
                </form>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($analytics['executive_kpis'] as $kpi)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase text-gray-500">{{ $kpi['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $kpi['value'] }}</p>
                        </div>
                        <span class="rounded-lg p-2 ring-1 {{ $badgeClasses[$kpi['color']] ?? $badgeClasses['gray'] }}">
                            <x-filament::icon :icon="$kpi['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $kpi['description'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Equipment Health Analytics</x-slot>
                <div class="space-y-4">
                    @foreach ($analytics['equipment_health'] as $row)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $row['label'] }}</span>
                                <span class="text-gray-600 dark:text-gray-400">{{ $row['count'] }} ({{ $row['percentage'] }}%)</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full bg-amber-500" style="width: {{ min(100, $row['percentage']) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Work Order Analytics</x-slot>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs uppercase text-gray-500">Total in period</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $analytics['work_orders']['total'] }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs uppercase text-gray-500">Completion rate</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $analytics['work_orders']['completion_rate'] }}%</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs uppercase text-gray-500">Avg completion time</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $analytics['work_orders']['average_completion_hours'] ?? 'N/A' }}<span class="text-sm font-normal"> hrs</span></p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs uppercase text-gray-500">Avg verification time</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $analytics['work_orders']['average_verification_hours'] ?? 'N/A' }}<span class="text-sm font-normal"> hrs</span></p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($analytics['work_orders']['statuses'] as $row)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950">
                            <span>{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Maintenance Schedule Analytics</x-slot>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($analytics['maintenance_schedules']['statuses'] as $row)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950">
                            <span>{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs uppercase text-gray-500">Preventive maintenance compliance</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $analytics['maintenance_schedules']['compliance_rate'] }}%</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Maintenance Request Analytics</x-slot>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($analytics['maintenance_requests']['statuses'] as $row)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950">
                            <span>{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs uppercase text-gray-500">Conversion rate</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $analytics['maintenance_requests']['conversion_rate'] }}%</p>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">AI Recommendation Analytics</x-slot>
            <div class="grid gap-4 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p class="text-xs uppercase text-gray-500">Generated recommendations</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $analytics['ai_recommendations']['generated'] }}</p>
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Status</p>
                    <div class="space-y-2">
                        @foreach ($analytics['ai_recommendations']['statuses'] as $row)
                            <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Risk</p>
                    <div class="space-y-2">
                        @foreach ($analytics['ai_recommendations']['risks'] as $row)
                            <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Action status</p>
                    <div class="space-y-2">
                        @foreach ($analytics['ai_recommendations']['actions'] as $row)
                            <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($analytics['ai_recommendations']['rules'] as $row)
                    <div class="flex justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-800">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ $row['count'] }}</strong>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No recommendation rules found for the selected period.</p>
                @endforelse
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Technician Performance Analytics</x-slot>
                @include('filament.pages.partials.analytics-table', [
                    'columns' => ['Technician / Staff', 'Assigned work orders', 'Accepted work orders', 'Completed work orders', 'Pending work orders', 'Average completion time'],
                    'rows' => collect($analytics['technician_performance'])->map(fn ($row) => [$row['name'], $row['assigned'], $row['accepted'], $row['completed'], $row['pending'], ($row['average_completion_hours'] ?? 'N/A').' hrs'])->all(),
                    'empty' => 'No technician work order activity found for the selected period.',
                ])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Equipment Reliability Analytics</x-slot>
                @include('filament.pages.partials.analytics-table', [
                    'columns' => ['Equipment code', 'Equipment name', 'Completed work orders', 'Open work orders', 'Maintenance requests', 'AI recommendations', 'Risk indicator'],
                    'rows' => collect($analytics['equipment_reliability'])->map(fn ($row) => [$row['equipment_code'], $row['equipment_name'], $row['completed_work_orders'], $row['open_work_orders'], $row['maintenance_requests'], $row['ai_recommendations'], $row['risk_indicator']])->all(),
                    'empty' => 'No equipment maintenance activity found.',
                ])
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Location-Based Analytics</x-slot>
            @include('filament.pages.partials.analytics-table', [
                'columns' => ['Location', 'Equipment count', 'Open work orders', 'Maintenance requests', 'Critical recommendations'],
                'rows' => collect($analytics['location_analytics'])->map(fn ($row) => [$row['location'], $row['equipment_count'], $row['open_work_orders'], $row['maintenance_requests'], $row['critical_recommendations']])->all(),
                'empty' => 'No location analytics available.',
            ])
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Lifecycle Decision Support</x-slot>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Replacement Candidates</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $analytics['lifecycle_widgets']['replacement_candidates'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">Critical Health Equipment</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $analytics['lifecycle_widgets']['critical_health_equipment'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase text-gray-500">High Maintenance Assets</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $analytics['lifecycle_widgets']['high_maintenance_assets'] }}</p>
                </div>
            </div>
            <div class="mt-4 grid gap-4 xl:grid-cols-3">
                @foreach ([
                    'Lowest Health Scores' => $analytics['lifecycle_widgets']['lowest_health_scores'],
                    'Highest Maintenance Cost' => $analytics['lifecycle_widgets']['highest_maintenance_cost'],
                    'Near End-of-Life Equipment' => $analytics['lifecycle_widgets']['near_end_of_life'],
                ] as $title => $rows)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                        <div class="mt-3 space-y-2">
                            @forelse ($rows as $row)
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950">
                                    <span class="min-w-0 truncate">{{ $row['equipment'] }}</span>
                                    <strong>{{ $row['value'] }}</strong>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No lifecycle records available.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">AI Recommendation Trend</x-slot>
                @include('filament.pages.partials.analytics-table', [
                    'columns' => ['Month', 'Generated', 'Resolved', 'Executed actions', 'Pending actions'],
                    'rows' => collect($analytics['recommendation_trend'])->map(fn ($row) => [$row['month'], $row['generated'], $row['resolved'], $row['executed_actions'], $row['pending_actions']])->all(),
                    'empty' => 'No recommendation trend data available.',
                ])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Maintenance Workload Trend</x-slot>
                @include('filament.pages.partials.analytics-table', [
                    'columns' => ['Month', 'Work orders created', 'Work orders completed', 'Maintenance requests submitted', 'Preventive schedules completed'],
                    'rows' => collect($analytics['workload_trend'])->map(fn ($row) => [$row['month'], $row['work_orders_created'], $row['work_orders_completed'], $row['maintenance_requests_submitted'], $row['preventive_schedules_completed']])->all(),
                    'empty' => 'No workload trend data available.',
                ])
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">System Insights</x-slot>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($analytics['insights'] as $insight)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        {{ $insight }}
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
