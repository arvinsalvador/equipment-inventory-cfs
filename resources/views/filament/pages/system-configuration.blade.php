<x-filament-panels::page>
    <form wire:submit="save" class="w-full space-y-6">
        @foreach ($this->settingsForDisplay() as $group => $groupSettings)
            <x-filament::section>
                <x-slot name="heading">{{ $this->groupLabel($group) }}</x-slot>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @foreach ($groupSettings as $setting)
                        <label class="block w-full min-w-0 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <span class="flex items-center justify-between gap-3 font-semibold">
                                <span>{{ $setting->label ?? str($setting->key)->replace('_', ' ')->title() }}</span>
                                @unless ($setting->isEditable())
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Locked</span>
                                @endunless
                            </span>

                            @if ($setting->description)
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $setting->description }}</span>
                            @endif

                            @if ($setting->isBoolean())
                                <span class="mt-3 flex items-center justify-between gap-4">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $setting->value_type }}</span>
                                    <input type="checkbox" wire:model="settings.{{ $group }}.{{ $setting->key }}" @disabled(! $setting->isEditable()) class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                </span>
                            @elseif (in_array($setting->value_type, ['text', 'json'], true))
                                <textarea wire:model="settings.{{ $group }}.{{ $setting->key }}" rows="{{ $setting->isJson() ? 6 : 3 }}" @disabled(! $setting->isEditable()) class="mt-3 block w-full min-w-0 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"></textarea>
                            @else
                                <input type="{{ $this->inputType($setting) }}" wire:model="settings.{{ $group }}.{{ $setting->key }}" @disabled(! $setting->isEditable()) @if ($setting->value_type === 'decimal') step="0.01" @endif class="mt-3 block w-full min-w-0 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            @endif

                            @error("settings.{$group}.{$setting->key}")
                                <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        <x-filament::button type="submit" icon="heroicon-o-check" :disabled="! auth()->user()?->can('system-settings.update')">
            Save Configuration
        </x-filament::button>
    </form>
</x-filament-panels::page>
