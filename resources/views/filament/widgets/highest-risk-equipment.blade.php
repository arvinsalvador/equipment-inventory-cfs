<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Highest Risk Equipment</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="py-2 pe-4 font-medium">Equipment Code</th>
                        <th class="py-2 pe-4 font-medium">Equipment Name</th>
                        <th class="py-2 pe-4 font-medium">Highest Risk</th>
                        <th class="py-2 font-medium">Open Recommendation Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-3 pe-4 font-medium text-gray-950 dark:text-white">{{ $row->equipment_code }}</td>
                            <td class="py-3 pe-4 text-gray-700 dark:text-gray-300">{{ $row->equipment_name }}</td>
                            <td class="py-3 pe-4 text-gray-700 dark:text-gray-300">{{ $row->highest_risk }}</td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ $row->open_recommendation_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-gray-500 dark:text-gray-400">No open recommendations.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
