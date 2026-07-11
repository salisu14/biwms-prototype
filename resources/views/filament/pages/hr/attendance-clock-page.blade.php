<x-filament-panels::page>
    <x-filament::section>
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <h2 class="text-lg font-semibold">Employee QR Clocking</h2>
                <p class="mt-1 text-sm text-gray-500">Scan or paste the signed employee ID-card token. Raw events are preserved and daily attendance is recalculated automatically.</p>
            </div>

            <form wire:submit="clock" class="space-y-4">
                <div>
                    <label for="cardToken" class="text-sm font-medium">Card token</label>
                    <input
                        id="cardToken"
                        type="password"
                        wire:model="cardToken"
                        autocomplete="off"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                    @error('cardToken')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="eventType" class="text-sm font-medium">Event</label>
                    <select
                        id="eventType"
                        wire:model="eventType"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="clock_in">Clock In</option>
                        <option value="clock_out">Clock Out</option>
                    </select>
                    @error('eventType')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-filament::button type="submit" icon="heroicon-o-qr-code">
                    Record Attendance
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-panels::page>
