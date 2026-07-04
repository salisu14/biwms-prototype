@component('auth.two-factor.layout', ['title' => 'Set up 2FA'])
    <h1>Set up 2FA</h1>
    <p>Scan the QR code with your authenticator app, then confirm your password and enter the current code to finish setup.</p>

    @if ($errors->any() || filled($errorMessage ?? null))
        <div class="error">
            {{ $errorMessage ?? $errors->first() }}
        </div>
    @endif

    <div class="qr-code" aria-label="Authenticator QR code">
        {!! $qrCodeSvg !!}
    </div>

    <label>Manual Secret</label>
    <div class="secret">{{ $secret }}</div>

    <label>Manual Setup URI</label>
    <div class="secret">{{ $otpauthUri }}</div>

    <form method="POST" action="{{ $action }}">
        @csrf
        <label for="password">Current password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <label for="code">Authenticator code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>

        <div class="actions">
            <button type="submit">Verify</button>
        </div>
    </form>
@endcomponent
