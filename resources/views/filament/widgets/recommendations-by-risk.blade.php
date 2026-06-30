<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Risk Distribution</x-slot>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->counts() as $risk => $count)
                @php
                    $classes = match ($risk) {
                        'Critical' => 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
                        'High' => 'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-200 dark:ring-orange-900',
                        'Moderate' => 'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-200 dark:ring-yellow-900',
                        default => 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                    };
                @endphp

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $risk }}</span>
                    <span class="inline-flex min-w-10 justify-center rounded-full px-2.5 py-1 text-sm font-semibold ring-1 {{ $classes }}">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
