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

            <textarea id="generated-recovery-codes-text" class="sr-only" readonly>{{ implode(PHP_EOL, $this->generatedRecoveryCodes) }}</textarea>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button type="button" color="gray" data-copy-generated-recovery-codes>
                    Copy
                </x-filament::button>

                <x-filament::button type="button" color="gray" data-download-generated-recovery-codes>
                    Download
                </x-filament::button>

                <x-filament::button type="button" color="gray" data-print-generated-recovery-codes>
                    Print
                </x-filament::button>
            </div>

            <script>
                (() => {
                    const recoveryCodes = document.getElementById('generated-recovery-codes-text')?.value ?? '';

                    document.querySelector('[data-copy-generated-recovery-codes]')?.addEventListener('click', async () => {
                        await navigator.clipboard.writeText(recoveryCodes);
                    });

                    document.querySelector('[data-download-generated-recovery-codes]')?.addEventListener('click', () => {
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(new Blob([recoveryCodes], { type: 'text/plain' }));
                        link.download = 'biwms-recovery-codes.txt';
                        link.click();
                        URL.revokeObjectURL(link.href);
                    });

                    document.querySelector('[data-print-generated-recovery-codes]')?.addEventListener('click', () => window.print());
                })();
            </script>
        </x-filament::section>
    @endif
</x-filament-panels::page>
