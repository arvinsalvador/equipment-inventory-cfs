@php
    use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;

    $riskClass = 'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900';
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Requires Immediate Attention</x-slot>
        <x-slot name="description">Critical recommendations, highest priority first.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <th class="py-3 pe-4 font-semibold">Equipment</th>
                        <th class="py-3 pe-4 font-semibold">Recommendation</th>
                        <th class="py-3 pe-4 font-semibold">Risk</th>
                        <th class="py-3 pe-4 font-semibold">Suggested Action</th>
                        <th class="py-3 pe-4 font-semibold">Generated</th>
                        <th class="py-3 font-semibold">Quick View</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->records() as $record)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-4 pe-4">
                                <div class="font-semibold text-gray-950 dark:text-white">{{ $record->equipment->equipment_code }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $record->equipment->equipment_name }}</div>
                            </td>
                            <td class="py-4 pe-4 text-gray-700 dark:text-gray-300">{{ $record->title }}</td>
                            <td class="py-4 pe-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $riskClass }}">
                                    {{ $record->risk_level }}
                                </span>
                            </td>
                            <td class="py-4 pe-4 text-gray-700 dark:text-gray-300">{{ $record->getSuggestedActionLabel() }}</td>
                            <td class="py-4 pe-4 text-gray-600 dark:text-gray-400">{{ $record->generated_at?->format('M d, Y g:i A') }}</td>
                            <td class="py-4">
                                <a href="{{ MaintenanceRecommendationResource::getUrl('view', ['record' => $record]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500">
                                    <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No critical recommendations require immediate attention.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
