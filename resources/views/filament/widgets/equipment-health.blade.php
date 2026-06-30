<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Equipment Health</x-slot>

        <div class="space-y-5">
            @foreach ($this->rows() as $row)
                @php
                    $bar = match ($row['color']) {
                        'green' => 'bg-green-500',
                        'yellow' => 'bg-yellow-500',
                        'orange' => 'bg-orange-500',
                        default => 'bg-red-500',
                    };
                @endphp

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['percentage'] }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full {{ $bar }}" style="width: {{ $row['percentage'] }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['count'] }} equipment records</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
