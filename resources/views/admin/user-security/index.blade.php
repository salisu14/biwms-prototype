@component('auth.two-factor.layout', ['title' => 'User Security', 'wide' => true])

    <div style="margin-bottom: 16px;">
        <a href="{{ url()->previous() ?? filament()->getUrl() }}"
           onclick="if(document.referrer) { history.back(); return false; }"
           style="display: inline-flex; align-items: center; gap: 6px; color: var(--muted); text-decoration: none; font-size: 14px; font-weight: 650;"
           onmouseover="this.style.color='var(--ink)'"
           onmouseout="this.style.color='var(--muted)'">
            &larr; Back
        </a>
    </div>

    <h1>User Security</h1>
    <p>Review account security status and manage 2FA enforcement for sensitive users.</p>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>User</th>
                <th>Roles</th>
                <th>Employee</th>
                <th>2FA</th>
                <th>Last Challenge</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}<br><small>{{ $user->email }}</small></td>
                    <td>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td>{{ $user->employee?->is_active === false ? 'Inactive' : ($user->employee ? 'Active' : '—') }}</td>
                    <td>
                            <span class="badge {{ $user->hasConfirmedTwoFactorAuthentication() ? 'badge-success' : 'badge-warning' }}">
                                {{ $user->hasConfirmedTwoFactorAuthentication() ? 'Enabled' : 'Disabled' }}
                            </span>
                        @if ($user->requiresTwoFactor())
                            <br><small>Required</small>
                        @endif
                    </td>
                    <td>{{ $user->two_factor_last_challenged_at?->toDateTimeString() ?? '—' }}</td>
                    <td class="action-cell">
                        <form method="POST" action="{{ route('admin.user-security.require-two-factor', $user) }}">@csrf<input name="password" type="password" autocomplete="current-password" placeholder="Password" required><button>Require 2FA</button></form>
                        <form method="POST" action="{{ route('admin.user-security.reset-two-factor', $user) }}">@csrf<input name="password" type="password" autocomplete="current-password" placeholder="Password" required><button>Force Reset</button></form>
                        <form method="POST" action="{{ route('admin.user-security.disable-two-factor', $user) }}">@csrf<input name="confirmation" placeholder="Own email only"><input name="password" type="password" autocomplete="current-password" placeholder="Password" required><button>Disable</button></form>
                        @if ($user->hasConfirmedTwoFactorAuthentication())
                            <form method="POST" action="{{ route('admin.user-security.regenerate-recovery-codes', $user) }}">@csrf<input name="password" type="password" autocomplete="current-password" placeholder="Password" required><button>Recovery Codes</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.user-security.clear-two-factor-session', $user) }}">@csrf<input name="password" type="password" autocomplete="current-password" placeholder="Password" required><button>Clear Session</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endcomponent
