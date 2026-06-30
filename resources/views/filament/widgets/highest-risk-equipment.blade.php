@php
    use App\Filament\Resources\Equipment\EquipmentResource;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Highest Risk Equipment</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <th class="py-3 pe-4 font-semibold">Equipment Code</th>
                        <th class="py-3 pe-4 font-semibold">Equipment Name</th>
                        <th class="py-3 pe-4 font-semibold">Risk Badge</th>
                        <th class="py-3 pe-4 font-semibold">Open Recommendations</th>
                        <th class="py-3 pe-4 font-semibold">Suggested Action</th>
                        <th class="py-3 font-semibold">View Equipment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows() as $row)
                        @php
                            $riskClass = match ($row->highest_risk) {
                                'Critical' => 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
                                'High' => 'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-200 dark:ring-orange-900',
                                'Moderate' => 'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-200 dark:ring-yellow-900',
                                default => 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                            };
                        @endphp

                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-4 pe-4 font-semibold text-gray-950 dark:text-white">{{ $row->equipment_code }}</td>
                            <td class="py-4 pe-4 text-gray-700 dark:text-gray-300">{{ $row->equipment_name }}</td>
                            <td class="py-4 pe-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $riskClass }}">{{ $row->highest_risk }}</span>
                            </td>
                            <td class="py-4 pe-4">
                                <span class="inline-flex min-w-10 justify-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">{{ $row->open_recommendation_count }}</span>
                            </td>
                            <td class="py-4 pe-4 text-gray-700 dark:text-gray-300">{{ $row->suggested_action }}</td>
                            <td class="py-4">
                                <a href="{{ EquipmentResource::getUrl('view', ['record' => $row->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                    <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No open recommendations.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
