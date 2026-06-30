@php
    use App\Filament\Resources\MaintenanceRecommendations\MaintenanceRecommendationResource;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent AI Recommendations</x-slot>

        <div class="grid gap-4">
            @forelse ($this->records() as $record)
                @php
                    $riskClass = match ($record->risk_level) {
                        'Critical' => 'border-red-500 bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
                        'High' => 'border-orange-500 bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-200 dark:ring-orange-900',
                        'Moderate' => 'border-yellow-500 bg-yellow-50 text-yellow-700 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-200 dark:ring-yellow-900',
                        default => 'border-green-500 bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                    };

                    $statusClass = match ($record->status) {
                        'Open' => 'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-900',
                        'Reviewed' => 'bg-purple-100 text-purple-700 ring-purple-200 dark:bg-purple-950 dark:text-purple-200 dark:ring-purple-900',
                        'Resolved' => 'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700',
                    };
                @endphp

                <article class="rounded-xl border border-l-4 bg-white p-4 shadow-sm dark:bg-gray-900 {{ $riskClass }}">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $record->equipment->equipment_code }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $record->equipment->equipment_name }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-gray-950 dark:text-white">{{ $record->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $record->explanation }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $riskClass }}">{{ $record->risk_level }}</span>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">{{ $record->status }}</span>
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">{{ $record->action_status ?: 'Pending' }}</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 text-sm xl:w-60">
                            <div class="rounded-lg bg-gray-50 p-3 text-gray-700 dark:bg-gray-950 dark:text-gray-300">
                                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Suggested Action</div>
                                <div class="mt-1 font-semibold">{{ $record->getSuggestedActionLabel() }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $record->generated_at?->format('M d, Y g:i A') }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ MaintenanceRecommendationResource::getUrl('view', ['record' => $record]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500">
                                    <x-filament::icon icon="heroicon-o-eye" class="h-3.5 w-3.5 shrink-0" />
                                    View
                                </a>
                                @can('approveAction', $record)
                                    @if ($record->isActionPending())
                                        <a href="{{ MaintenanceRecommendationResource::getUrl('view', ['record' => $record]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-200 dark:hover:bg-blue-950">
                                            <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5 shrink-0" />
                                            Approve
                                        </a>
                                    @endif
                                @endcan
                                @can('rejectAction', $record)
                                    @if ($record->isActionPending())
                                        <a href="{{ MaintenanceRecommendationResource::getUrl('view', ['record' => $record]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-200 dark:hover:bg-red-950">
                                            <x-filament::icon icon="heroicon-o-x-mark" class="h-3.5 w-3.5 shrink-0" />
                                            Reject
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    No open recommendations.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
