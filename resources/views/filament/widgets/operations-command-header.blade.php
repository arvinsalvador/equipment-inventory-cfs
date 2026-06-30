<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-5 p-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0 max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800">
                    <x-filament::icon icon="heroicon-o-command-line" class="h-3.5 w-3.5 shrink-0" />
                    Operations Command Center
                </div>

                <h1 class="mt-4 text-2xl font-semibold tracking-normal text-gray-950 dark:text-white">
                    Smart Maintenance Command Center
                </h1>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Rule-based AI maintenance recommendations generated from equipment, maintenance schedules,
                    maintenance requests, work orders and evidence.
                </p>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-[28rem]">
                <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                    <div class="flex items-center gap-2 text-[11px] font-medium uppercase leading-4 text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-sparkles" class="h-3.5 w-3.5 shrink-0" />
                        Last Recommendation Scan
                    </div>
                    <div class="mt-2 text-xs font-semibold leading-5 text-gray-950 dark:text-white">
                        {{ $this->lastRecommendationScan() ?? 'No scans yet' }}
                    </div>
                </div>

                <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                    <div class="flex items-center gap-2 text-[11px] font-medium uppercase leading-4 text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-clock" class="h-3.5 w-3.5 shrink-0" />
                        Last Updated
                    </div>
                    <div class="mt-2 text-xs font-semibold leading-5 text-gray-950 dark:text-white">
                        {{ $this->lastUpdated() ?? now()->toDayDateTimeString() }}
                    </div>
                </div>

                <a
                    href="{{ request()->fullUrl() }}"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 sm:col-span-2"
                >
                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 shrink-0" />
                    Refresh Dashboard
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
