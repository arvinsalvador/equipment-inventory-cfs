<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recommendations by Risk</x-slot>

        <div class="grid gap-4 md:grid-cols-4">
            @foreach ($this->counts() as $risk => $count)
                @php
                    $classes = match ($risk) {
                        'Critical' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
                        'High' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
                        'Moderate' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
                        default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200',
                    };
                @endphp
                <div class="rounded-lg border p-4 shadow-sm {{ $classes }}">
                    <div class="text-sm font-medium">{{ $risk }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $count }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
