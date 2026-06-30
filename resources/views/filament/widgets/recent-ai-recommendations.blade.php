<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent AI Recommendations</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pe-4 font-medium">Equipment</th>
                        <th class="py-2 pe-4 font-medium">Title</th>
                        <th class="py-2 pe-4 font-medium">Risk</th>
                        <th class="py-2 pe-4 font-medium">Suggested Action</th>
                        <th class="py-2 font-medium">Generated Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->records() as $record)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-3 pe-4 font-medium text-gray-950 dark:text-white">{{ $record->equipment->equipment_code }}</td>
                            <td class="py-3 pe-4 text-gray-700 dark:text-gray-300">{{ $record->title }}</td>
                            <td class="py-3 pe-4 text-gray-700 dark:text-gray-300">{{ $record->risk_level }}</td>
                            <td class="py-3 pe-4 text-gray-700 dark:text-gray-300">{{ $record->getSuggestedActionLabel() }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ $record->generated_at?->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-gray-500 dark:text-gray-400">No open recommendations.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
