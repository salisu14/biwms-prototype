<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500">Period</div>
            <div class="mt-2 text-lg font-semibold">{{ $from->toFormattedDateString() }} - {{ $until->toFormattedDateString() }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Worked Hours</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($workedHours, 2) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Late Minutes</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($lateMinutes) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500">Overtime Minutes</div>
            <div class="mt-2 text-3xl font-semibold">{{ number_format($overtimeMinutes) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Payroll review queue</h2>
                <p class="mt-1 text-sm text-gray-500">Attendance flags are informational. Payroll documents are not modified automatically.</p>
            </div>
            <div class="text-3xl font-semibold">{{ number_format($payrollReviewCount) }}</div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
