<x-filament-panels::page>
    <div class="space-y-4">
        @forelse ($requests as $request)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ $request->employee?->full_name }} · {{ $request->leaveType?->name }}</div>
                        <div class="text-sm text-gray-500">{{ $request->start_date?->format('M d, Y') }} - {{ $request->end_date?->format('M d, Y') }} · {{ number_format((float) $request->requested_quantity, 2) }} day(s)</div>
                    </div>
                    <div class="text-sm font-semibold uppercase">{{ str_replace('_', ' ', $request->status) }}</div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                No leave approvals are waiting.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
