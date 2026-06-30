<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Maintenance Schedule Summary</x-slot>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->counts() as $label => $count)
                @php
                    $classes = match ($label) {
                        'Due Today' => 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-900',
                        'Due This Week' => 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                        'Overdue' => 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700',
                    };
                @endphp

                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-gray-50 p-2 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </div>
                    <span class="inline-flex min-w-10 justify-center rounded-full px-2.5 py-1 text-sm font-semibold ring-1 {{ $classes }}">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
