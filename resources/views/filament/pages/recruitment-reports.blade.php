<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-filament::section>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Report Period</div>
                <div class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $from->toFormattedDateString() }} to {{ $until->toFormattedDateString() }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">New Applications</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($newApplications) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tracked Stages</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format(count($applicationsByStage)) }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Offer Statuses</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format(count($offersByStatus)) }}</div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([
                'Applications by Stage' => $applicationsByStage,
                'Applications by Status' => $applicationsByStatus,
                'Vacancies by Status' => $vacanciesByStatus,
                'Offers by Status' => $offersByStatus,
            ] as $heading => $rows)
                <x-filament::section :heading="$heading">
                    <div class="space-y-3">
                        @forelse ($rows as $label => $count)
                            <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ str((string) $label)->headline() }}</span>
                                <span class="text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($count) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No records are available for this summary yet.</p>
                        @endforelse
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
