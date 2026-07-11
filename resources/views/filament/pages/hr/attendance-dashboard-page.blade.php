<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500">Present Today</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($presentToday) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Late Today</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($lateToday) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Missing Clock-out</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($missingClockOutToday) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Payroll Review</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($payrollReviewCount) }}</div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
