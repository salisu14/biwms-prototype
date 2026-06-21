@component('auth.two-factor.layout', ['title' => 'Set up 2FA'])
    <h1>Set up 2FA</h1>
    <p>Add this TOTP secret to your authenticator app, then enter the current code to finish setup.</p>

    @if ($errors->any() || filled($errorMessage ?? null))
        <div class="error">
            {{ $errorMessage ?? $errors->first() }}
        </div>
    @endif

    <label>TOTP Secret</label>
    <div class="secret">{{ $secret }}</div>

    <form method="POST" action="{{ $action }}">
        @csrf
        <label for="code">Authenticator code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>

        <div class="actions">
            <button type="submit">Verify</button>
        </div>
    </form>
@endcomponent
