<x-filament-panels::page>
    <div class="space-y-4">
        @forelse ($requests as $request)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ $request->request_number }}</div>
                        <div class="text-sm text-gray-500">{{ $request->leaveType?->name }} · {{ $request->start_date?->format('M d, Y') }} - {{ $request->end_date?->format('M d, Y') }}</div>
                    </div>
                    <div class="text-sm font-semibold uppercase">{{ str_replace('_', ' ', $request->status) }}</div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                No leave requests found.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
