<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <form wire:submit="mount">
                {{ $this->form }}
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
        .fi-ta-cell {
            @apply px-2 py-1 !important;
        }
        .fi-ta-header-cell {
            @apply px-2 py-1 !important;
        }
    </style>
</x-filament-panels::page>
