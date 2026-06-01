<x-filament-panels::page>
    @include('filament.components.report-table-styles', ['includeFilamentTable' => true])
    <div class="space-y-6">
        <x-filament::section>
            <form wire:submit="generateReport" class="p-2">
                {{ $this->form }}
                <div class="mt-4 flex justify-end items-center gap-x-4 border-t border-gray-100 dark:border-white/5 pt-4">
                    <button type="button" wire:click="form.fill" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        Reset Filters
                    </button>
                    <x-filament::button type="submit" size="lg" class="rounded-xl shadow-lg shadow-primary-500/20 px-6">
                        Update Analysis
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <div class="overflow-x-auto min-h-[400px]">
                {{ $this->table }}
            </div>
        </x-filament::section>
    </div>

    <style>
        /* Custom styling for the report to make it feel dense and premium */
        .fi-ta-header-group-label {
            @apply border-b border-gray-200 dark:border-white/10 text-center py-1 font-bold text-xs uppercase tracking-wider;
            background-color: rgba(var(--primary-50), 0.5);
        }
    </style>
</x-filament-panels::page>
