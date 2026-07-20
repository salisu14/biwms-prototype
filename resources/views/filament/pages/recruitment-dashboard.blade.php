<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ([
                'Open Requisitions' => $openRequisitions,
                'Open Vacancies' => $openVacancies,
                'Active Candidates' => $activeCandidates,
                'Active Applications' => $activeApplications,
                'Pending Offers' => $pendingOffers,
                'Onboarding Plans' => $activeOnboardingPlans,
            ] as $label => $value)
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section heading="Recent Applications">
                <div class="space-y-3">
                    @forelse ($recentApplications as $application)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-gray-950 dark:text-white">
                                        {{ $application->application_number }}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $application->candidate?->first_name }} {{ $application->candidate?->last_name }}
                                        <span class="mx-1">/</span>
                                        {{ $application->vacancy?->title ?? 'Unassigned vacancy' }}
                                    </div>
                                </div>
                                <x-filament::badge>
                                    {{ str($application->status)->headline() }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent applications yet.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Recent Offers">
                <div class="space-y-3">
                    @forelse ($recentOffers as $offer)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-gray-950 dark:text-white">
                                        {{ $offer->offer_number }}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $offer->application?->candidate?->first_name }} {{ $offer->application?->candidate?->last_name }}
                                        <span class="mx-1">/</span>
                                        {{ $offer->proposed_start_date?->toFormattedDateString() ?? 'No start date' }}
                                    </div>
                                </div>
                                <x-filament::badge>
                                    {{ str($offer->status)->headline() }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No offers have been issued yet.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
