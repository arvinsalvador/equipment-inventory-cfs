<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">AI Recommendation Summary</x-slot>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->counts() as $label => $count)
                @php
                    $classes = match ($label) {
                        'Open Recommendations' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
                        'Reviewed Recommendations' => 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-900 dark:bg-purple-950 dark:text-purple-200',
                        'Resolved Recommendations' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
                        'Dismissed Recommendations' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200',
                        'Critical Recommendations' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
                        'High Recommendations' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
                        'Moderate Recommendations' => 'border-yellow-200 bg-yellow-50 text-yellow-700 dark:border-yellow-900 dark:bg-yellow-950 dark:text-yellow-200',
                        default => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
                    };
                @endphp

                <div class="rounded-xl border p-4 shadow-sm {{ $classes }}">
                    <div class="text-sm font-medium">{{ str_replace(' Recommendations', '', $label) }}</div>
                    <div class="mt-3 text-3xl font-semibold tracking-normal">{{ $count }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
