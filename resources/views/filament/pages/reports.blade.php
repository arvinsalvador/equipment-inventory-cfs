<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->reports() as $slug => $report)
            <x-filament::section>
                <div class="flex h-full flex-col gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $report['name'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $report['description'] }}</p>
                    </div>

                    <div class="mt-auto flex flex-wrap gap-2">
                        <x-filament::button :href="route('reports.show', $slug)" tag="a" size="sm" icon="heroicon-o-eye">
                            View
                        </x-filament::button>

                        @if (in_array('print', $report['actions'], true))
                            <x-filament::button :href="route('reports.print', $slug)" tag="a" size="sm" color="gray" icon="heroicon-o-printer">
                                Print
                            </x-filament::button>
                        @endif

                        @if (in_array('csv', $report['actions'], true))
                            <x-filament::button :href="route('reports.csv', $slug)" tag="a" size="sm" color="gray" icon="heroicon-o-arrow-down-tray">
                                CSV
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
