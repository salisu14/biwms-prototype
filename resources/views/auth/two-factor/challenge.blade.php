@component('auth.two-factor.layout', ['title' => '2FA Challenge'])
    <h1>2FA Challenge</h1>
    <p>Enter an authenticator code or one of your recovery codes to continue to the admin panel.</p>

    @if ($errors->any() || filled($errorMessage ?? null))
        <div class="error">
            {{ $errorMessage ?? $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        <label for="code">Code</label>
        <input id="code" name="code" autocomplete="one-time-code" required autofocus>

        <div class="actions">
            <button type="submit">Verify</button>
        </div>
    </form>
@endcomponent
