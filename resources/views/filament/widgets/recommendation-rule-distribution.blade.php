<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recommendation Distribution</x-slot>

        @php
            $counts = $this->counts();
            $max = max(max($counts), 1);
        @endphp

        <div class="space-y-4">
            @foreach ($counts as $rule => $count)
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $rule }}</span>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $count }}</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-primary-600" style="width: {{ (int) round(($count / $max) * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
