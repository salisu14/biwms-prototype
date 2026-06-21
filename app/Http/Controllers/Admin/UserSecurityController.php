<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserSecurityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $query = User::query()
            ->with(['employee', 'roles'])
            ->orderBy('name');

        if ($request->query('two_factor') === 'enabled') {
            $query->whereNotNull('two_factor_confirmed_at');
        }

        if ($request->query('two_factor') === 'disabled') {
            $query->whereNull('two_factor_confirmed_at');
        }

        if ($role = $request->query('role')) {
            $query->role((string) $role);
        }

        if ($request->boolean('inactive_employee')) {
            $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('is_active', false));
        }

        return view('admin.user-security.index', [
            'users' => $query->get(),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function requireTwoFactor(Request $request, User $user, AuditTrailService $auditTrailService): RedirectResponse
    {
        $actor = $this->authorizeSuperAdmin($request);

        $user->forceFill(['two_factor_required' => true])->save();

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_required_enabled',
            auditable: $user,
            userId: $actor->id,
            description: 'Super Admin required 2FA for user',
        );

        return back();
    }

    public function disableTwoFactor(Request $request, User $user, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): RedirectResponse|Response
    {
        $actor = $this->authorizeSuperAdmin($request);

        if ($actor->is($user) && $request->input('confirmation') !== $actor->email) {
            return response('Type your email address to confirm disabling your own 2FA.', 422);
        }

        $user->forceFill(['two_factor_required' => false])->save();
        $twoFactorService->disable($user, $actor->id);

        if ($actor->is($user)) {
            $request->session()->forget(['two_factor_passed_at', 'super_admin_2fa_passed_at']);
        }

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_admin_disabled',
            auditable: $user,
            userId: $actor->id,
            description: 'Super Admin disabled 2FA for user',
        );

        return back();
    }

    public function resetTwoFactor(Request $request, User $user, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): RedirectResponse|Response
    {
        $actor = $this->authorizeSuperAdmin($request);

        if ($actor->is($user) && $request->input('confirmation') !== $actor->email) {
            return response('Type your email address to confirm resetting your own 2FA.', 422);
        }

        $twoFactorService->forceReset($user, $actor->id);

        if ($actor->is($user)) {
            $request->session()->forget(['super_admin_2fa_setup_secret', 'two_factor_passed_at', 'super_admin_2fa_passed_at']);
        }

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_admin_reset',
            auditable: $user,
            userId: $actor->id,
            description: 'Super Admin reset 2FA for user',
        );

        return back();
    }

    public function regenerateRecoveryCodes(Request $request, User $user, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): View
    {
        $actor = $this->authorizeSuperAdmin($request);

        abort_unless($user->hasConfirmedTwoFactorAuthentication(), 404);

        $plainRecoveryCodes = $twoFactorService->regenerateRecoveryCodes($user);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_recovery_codes_regenerated',
            auditable: $user,
            userId: $actor->id,
            description: 'Super Admin regenerated 2FA recovery codes for user',
            metadata: ['recovery_code_count' => count($plainRecoveryCodes)],
        );

        return view('auth.two-factor.recovery-codes', [
            'codes' => $plainRecoveryCodes,
            'continueUrl' => route('admin.user-security.index'),
        ]);
    }

    public function clearCurrentTwoFactorSession(Request $request, User $user, AuditTrailService $auditTrailService): RedirectResponse
    {
        $actor = $this->authorizeSuperAdmin($request);

        if ($actor->is($user)) {
            $request->session()->forget(['two_factor_passed_at', 'super_admin_2fa_passed_at']);
        }

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_session_cleared',
            auditable: $user,
            userId: $actor->id,
            description: 'Super Admin cleared current 2FA session',
        );

        return back();
    }

    private function authorizeSuperAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless($user?->hasRole('super_admin'), 404);

        return $user;
    }
}
