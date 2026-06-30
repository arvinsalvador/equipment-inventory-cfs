<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">AI Recommendation Overview</x-slot>

        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            Rule-based maintenance recommendations generated from equipment, maintenance, work order, and evidence records.
        </p>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($this->counts() as $label => $count)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $count }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
