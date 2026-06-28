@component('auth.two-factor.layout', ['title' => 'Two-Factor Authentication'])
    <h1>Two-Factor Authentication</h1>
    <p>Manage your authenticator app and recovery codes for admin access.</p>

    @if (filled($statusMessage ?? null))
        <div class="notice">{{ $statusMessage }}</div>
    @endif

    @if ($errors->any() || filled($errorMessage ?? null))
        <div class="error">
            {{ $errorMessage ?? $errors->first() }}
        </div>
    @endif

    <dl class="status-list">
        <div><dt>Status</dt><dd><span class="badge {{ $user->hasConfirmedTwoFactorAuthentication() ? 'badge-success' : 'badge-warning' }}">{{ $user->hasConfirmedTwoFactorAuthentication() ? 'Enabled' : 'Disabled' }}</span></dd></div>
        <div><dt>Required</dt><dd>{{ $user->requiresTwoFactor() ? 'Yes' : 'No' }}</dd></div>
        <div><dt>Confirmed at</dt><dd>{{ $user->two_factor_confirmed_at?->toDayDateTimeString() ?? '—' }}</dd></div>
        <div><dt>Recovery codes remaining</dt><dd>{{ $user->twoFactorRecoveryCodesRemaining() }}</dd></div>
        <div><dt>Last challenge</dt><dd>{{ $user->two_factor_last_challenged_at?->toDayDateTimeString() ?? '—' }}</dd></div>
    </dl>

    <div class="stack">
        @unless ($user->hasConfirmedTwoFactorAuthentication())
            <a class="button" href="{{ route('admin.two-factor.setup.create') }}">Enable 2FA</a>
        @else
            <form method="POST" action="{{ route('admin.two-factor.recovery-codes.regenerate') }}">
                @csrf
                <label for="recovery-codes-password">Confirm your password</label>
                <input id="recovery-codes-password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Regenerate Recovery Codes</button>
            </form>

            <form method="POST" action="{{ route('admin.two-factor.reset-authenticator') }}">
                @csrf
                <label for="reset-authenticator-password">Confirm your password</label>
                <input id="reset-authenticator-password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Reset Authenticator App</button>
            </form>

            <form method="POST" action="{{ route('admin.two-factor.disable') }}">
                @csrf
                <label for="confirmation">Type your email to disable 2FA</label>
                <input id="confirmation" name="confirmation" autocomplete="off">
                <label for="disable-password">Confirm your password</label>
                <input id="disable-password" name="password" type="password" autocomplete="current-password" required>
                <div class="actions">
                    <button type="submit">Disable 2FA</button>
                </div>
            </form>
        @endunless
    </div>
@endcomponent
