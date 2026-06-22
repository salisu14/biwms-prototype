<x-filament-panels::page>
    {{ $this->table }}

    @if ($this->generatedRecoveryCodes !== [])
        <x-filament::section>
            <x-slot name="heading">
                Recovery Codes
            </x-slot>

            <x-slot name="description">
                New recovery codes for {{ $this->generatedRecoveryCodesFor }}. Copy them now; they will not be shown again after leaving this page.
            </x-slot>

            <div class="grid gap-2 font-mono text-sm">
                @foreach ($this->generatedRecoveryCodes as $code)
                    <div class="rounded-md bg-gray-100 px-3 py-2 text-gray-950 dark:bg-gray-800 dark:text-gray-100">
                        {{ $code }}
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
