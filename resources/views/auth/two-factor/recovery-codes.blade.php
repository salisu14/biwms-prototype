@component('auth.two-factor.layout', ['title' => 'Recovery Codes'])
    <h1>Recovery Codes</h1>
    <p>Store these recovery codes securely. They will not be shown again, and each code can only be used once.</p>

    <ol class="codes">
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ol>

    <textarea id="recovery-codes-text" class="sr-only" readonly>{{ implode(PHP_EOL, $codes) }}</textarea>

    <div class="actions">
        <button type="button" data-copy-recovery-codes>Copy</button>
        <button type="button" data-download-recovery-codes>Download</button>
        <button type="button" data-print-recovery-codes>Print</button>
        <a class="button" href="{{ $continueUrl }}">Continue</a>
    </div>

    <script>
        (() => {
            const recoveryCodes = document.getElementById('recovery-codes-text')?.value ?? '';

            document.querySelector('[data-copy-recovery-codes]')?.addEventListener('click', async () => {
                await navigator.clipboard.writeText(recoveryCodes);
            });

            document.querySelector('[data-download-recovery-codes]')?.addEventListener('click', () => {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob([recoveryCodes], { type: 'text/plain' }));
                link.download = 'biwms-recovery-codes.txt';
                link.click();
                URL.revokeObjectURL(link.href);
            });

            document.querySelector('[data-print-recovery-codes]')?.addEventListener('click', () => window.print());
        })();
    </script>
@endcomponent
