<x-filament-panels::page>
    <div class="space-y-6">
        <header class="p-6 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-800 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-primary-50 dark:bg-primary-950">
                    <x-heroicon-o-check-badge class="w-7 h-7 text-primary-600" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        Finished Goods
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Manage your inventory of completed items ready for shipping.
                    </p>
                </div>
            </div>
        </header>

        {{-- This specific call ensures the table initialized in the class is rendered --}}
        <div class="filament-main-content">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
