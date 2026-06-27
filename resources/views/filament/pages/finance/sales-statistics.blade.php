<x-filament-panels::page>
    @include('filament.components.report-table-styles')

    <div class="space-y-6">
        <x-filament::section>
            <form wire:submit="generate" class="space-y-4">
                {{ $this->form }}
                <div class="flex justify-end">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-path">
                        Refresh
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @include('filament.pages.finance.partials.statistics-report', ['report' => $report])
    </div>
</x-filament-panels::page>
