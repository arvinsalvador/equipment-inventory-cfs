<x-filament-panels::page>
    @php
        $insights = $this->insights();
        $currency = fn ($value) => 'PHP '.number_format((float) $value, 2);
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Reports and Analytics</p>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">Executive Decision Support</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Rule-based strategic summary of equipment condition, lifecycle risk, maintenance burden, budget readiness, and AI recommendation decisions.
                    </p>
                </div>
                <span class="rounded-md bg-gray-50 px-2 py-1 text-xs text-gray-600 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800">
                    Generated: {{ $insights['generated_at'] }}
                </span>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($insights['kpis'] as $kpi)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500">{{ $kpi['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $kpi['value'] }}</p>
                    <p class="mt-3 text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $kpi['description'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Strategic Insights</x-slot>
                <div class="space-y-2">
                    @foreach ($insights['strategic_insights'] as $message)
                        <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ $message }}</div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recommended Management Actions</x-slot>
                <div class="space-y-2">
                    @foreach ($insights['action_items'] as $item)
                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">{{ $item }}</div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Risk Priority Matrix</x-slot>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                            <th class="px-3 py-2">Risk</th>
                            <th class="px-3 py-2">Equipment count</th>
                            <th class="px-3 py-2">Open recommendations</th>
                            <th class="px-3 py-2">Open work orders</th>
                            <th class="px-3 py-2">Estimated budget impact</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-900">
                        @foreach ($insights['risk_matrix'] as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $row['risk_level'] }}</td>
                                <td class="px-3 py-2">{{ $row['equipment_count'] }}</td>
                                <td class="px-3 py-2">{{ $row['open_recommendations'] }}</td>
                                <td class="px-3 py-2">{{ $row['open_work_orders'] }}</td>
                                <td class="px-3 py-2">{{ $currency($row['estimated_budget_impact']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-filament::section>
                <x-slot name="heading">Replacement Forecast Summary</x-slot>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Replacement candidates this year</span><strong>{{ $insights['replacement_forecast']['replacement_candidates_this_year'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Replacement candidates next year</span><strong>{{ $insights['replacement_forecast']['replacement_candidates_next_year'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Estimated replacement cost</span><strong>{{ $currency($insights['replacement_forecast']['estimated_replacement_cost']) }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Critical lifecycle equipment</span><strong>{{ $insights['replacement_forecast']['critical_lifecycle_equipment'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Beyond-repair equipment</span><strong>{{ $insights['replacement_forecast']['beyond_repair_equipment'] }}</strong></div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Budget Decision Summary</x-slot>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Approved budget plans</span><strong>{{ $insights['budget_summary']['approved_budget_plans'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Pending budget plans</span><strong>{{ $insights['budget_summary']['pending_budget_plans'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Total proposed budget</span><strong>{{ $currency($insights['budget_summary']['total_proposed_budget']) }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Total approved budget</span><strong>{{ $currency($insights['budget_summary']['total_approved_budget']) }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Critical budget items</span><strong>{{ $insights['budget_summary']['critical_budget_items'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Deferred budget items</span><strong>{{ $insights['budget_summary']['deferred_budget_items'] }}</strong></div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">AI Recommendation Decision Summary</x-slot>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Open AI recommendations</span><strong>{{ $insights['recommendation_summary']['open_recommendations'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Approved actions</span><strong>{{ $insights['recommendation_summary']['approved_actions'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Executed actions</span><strong>{{ $insights['recommendation_summary']['executed_actions'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Rejected actions</span><strong>{{ $insights['recommendation_summary']['rejected_actions'] }}</strong></div>
                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-950"><span>Pending actions</span><strong>{{ $insights['recommendation_summary']['pending_actions'] }}</strong></div>
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Maintenance Burden Summary</x-slot>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Work order completion rate: <strong>{{ $insights['maintenance_burden']['work_order_completion_rate'] }}%</strong></p>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ([
                        'Most frequently repaired equipment' => $insights['maintenance_burden']['most_frequently_repaired_equipment'],
                        'Locations with most work orders' => $insights['maintenance_burden']['locations_with_most_work_orders'],
                        'Technicians with highest workload' => $insights['maintenance_burden']['technicians_with_highest_workload'],
                        'Equipment with repeated repairs' => $insights['maintenance_burden']['equipment_with_repeated_repairs'],
                    ] as $title => $rows)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                            <div class="mt-2 space-y-2">
                                @forelse ($rows as $row)
                                    <div class="flex justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-950"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong></div>
                                @empty
                                    <p class="text-sm text-gray-500">No records available.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Top Risks</x-slot>
                <div class="space-y-2">
                    @forelse ($insights['top_risks'] as $risk)
                        <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $risk['equipment'] }}</p>
                                    <p class="text-gray-500">{{ $risk['location'] }}</p>
                                </div>
                                <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-600/20 dark:bg-red-950 dark:text-red-200">{{ $risk['risk_level'] }}</span>
                            </div>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">Open recommendations: {{ $risk['open_recommendations'] }} | Open work orders: {{ $risk['open_work_orders'] }} | Repairs: {{ $risk['completed_repairs'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No high-risk equipment identified.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Most Common Recommendation Rules</x-slot>
            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($insights['recommendation_summary']['common_rules'] as $rule)
                    <div class="flex justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-800">
                        <span>{{ $rule['rule'] }}</span>
                        <strong>{{ $rule['count'] }}</strong>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No recommendation rules available.</p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
