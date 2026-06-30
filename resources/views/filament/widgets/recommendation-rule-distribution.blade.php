<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recommendation Rule Distribution</x-slot>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->counts() as $rule => $count)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="break-words text-sm font-medium text-gray-600 dark:text-gray-300">{{ $rule }}</div>
                    <div class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ $count }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
