<x-filament-panels::page>
    @php
        $readiness = $this->readiness();
        $statusClasses = [
            'ready' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-950 dark:text-green-200',
            'review' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950 dark:text-blue-200',
            'warning' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-950 dark:text-yellow-200',
            'critical' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950 dark:text-red-200',
        ];
        $renderChecklist = function (array $items) use ($statusClasses) {
            return view('filament.pages.partials.production-readiness-checklist', [
                'items' => $items,
                'statusClasses' => $statusClasses,
            ]);
        };
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">System Administration</p>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">Production Readiness</h1>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Read-only deployment readiness review for shared hosting, security, performance, backups, queue operations, storage, and PWA behavior.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-4 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500">Overall Readiness Score</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $readiness['score'] }}%</p>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Security Checklist</x-slot>
                {{ $renderChecklist($readiness['security']) }}
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Performance Checklist</x-slot>
                {{ $renderChecklist($readiness['performance']) }}
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Deployment Checklist</x-slot>
                {{ $renderChecklist($readiness['deployment']) }}
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Backup and Restore Checklist</x-slot>
                {{ $renderChecklist($readiness['backup']) }}
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Queue and Notification Checklist</x-slot>
                {{ $renderChecklist($readiness['queue']) }}
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Storage and File Upload Checklist</x-slot>
                {{ $renderChecklist($readiness['storage']) }}
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">PWA Readiness Checklist</x-slot>
            {{ $renderChecklist($readiness['pwa']) }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Android PWA/TWA Readiness Checklist</x-slot>
            {{ $renderChecklist($readiness['android']) }}
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Production Warnings</x-slot>
                <div class="space-y-2">
                    @foreach ($readiness['warnings'] as $warning)
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 dark:border-yellow-900 dark:bg-yellow-950 dark:text-yellow-200">
                            {{ $warning }}
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recommended Actions</x-slot>
                <div class="space-y-2">
                    @foreach ($readiness['actions'] as $action)
                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                            {{ $action }}
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
