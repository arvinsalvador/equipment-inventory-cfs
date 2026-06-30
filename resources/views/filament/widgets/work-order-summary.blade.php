<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Work Order Summary</x-slot>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->counts() as $label => $count)
                @php
                    $classes = match ($label) {
                        'Available' => 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-900',
                        'Assigned', 'Accepted' => 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-900',
                        'In Progress' => 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                        'On Hold' => 'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-200 dark:ring-yellow-900',
                        'Completed Today' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700',
                    };
                @endphp

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-gray-50 p-2 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-5 w-5" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </div>
                    <span class="inline-flex min-w-10 justify-center rounded-full px-2.5 py-1 text-sm font-semibold ring-1 {{ $classes }}">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
