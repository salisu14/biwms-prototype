<?php

use App\Enums\PayrollStatus;
use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Events\PayrollPosted;
use App\Filament\Resources\AuditTrails\AuditTrailResource;
use App\Models\AuditTrail;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PayrollDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('records payment application and reversal audit trails from posting events', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'payment_number' => 'PAY-AUD-001',
        'status' => 'POSTED',
        'created_by' => $user->id,
        'posted_by' => $user->id,
    ]);

    $application = PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'document_type' => 'SALES_INVOICE',
        'document_id' => 1001,
        'document_number' => 'INV-AUD-001',
        'document_original_amount' => 1000,
        'document_remaining_before' => 1000,
        'amount_applied' => 250,
        'amount_applied_lcy' => 250,
        'gain_loss_amount' => 0,
        'discount_applied' => 0,
        'write_off_amount' => 0,
        'document_remaining_after' => 750,
        'full_payment' => false,
        'applied_by' => $user->id,
        'applied_at' => now(),
    ]);

    PaymentApplied::dispatch($application);

    expect(AuditTrail::query()
        ->where('event_type', 'payment')
        ->where('action', 'applied')
        ->where('document_no', 'INV-AUD-001')
        ->where('auditable_type', $application->getMorphClass())
        ->where('auditable_id', $application->id)
        ->exists())->toBeTrue();

    $application->update([
        'reversed' => true,
        'reversed_by' => $user->id,
        'reversed_at' => now(),
    ]);

    PaymentUnapplied::dispatch($application->fresh());

    expect(AuditTrail::query()
        ->where('event_type', 'reversal')
        ->where('action', 'payment_unapplied')
        ->where('document_no', 'INV-AUD-001')
        ->where('source_type', $payment->getMorphClass())
        ->where('source_id', $payment->id)
        ->exists())->toBeTrue();
});

it('records payroll posting audit trails from domain events', function () {
    $document = PayrollDocument::query()->create([
        'document_number' => 'PRL-AUD-001',
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'status' => PayrollStatus::POSTED,
        'total_earnings' => 5000,
        'total_deductions' => 1000,
        'total_net_pay' => 4000,
    ]);

    PayrollPosted::dispatch($document);

    $auditTrail = AuditTrail::query()
        ->where('event_type', 'payroll')
        ->where('action', 'posted')
        ->where('document_no', 'PRL-AUD-001')
        ->first();

    expect($auditTrail)->not->toBeNull()
        ->and($auditTrail->metadata['total_net_pay'])->toBe('4000.00');
});

it('records permission grants and setup changes', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();
    $permission = Permission::query()->create([
        'name' => 'audit_trail.view',
        'guard_name' => 'web',
    ]);

    $this->actingAs($actor);

    $user->givePermissionTo($permission);

    expect(AuditTrail::query()
        ->where('event_type', 'permission')
        ->where('action', 'permission_granted')
        ->where('auditable_type', $user->getMorphClass())
        ->where('auditable_id', $user->id)
        ->where('user_id', $actor->id)
        ->exists())->toBeTrue();

    $bankAccount = BankAccount::factory()->create(['bank_name' => 'Old Bank']);
    $bankAccount->update(['bank_name' => 'New Bank']);

    $auditTrail = AuditTrail::query()
        ->where('event_type', 'setup')
        ->where('action', 'updated')
        ->where('auditable_type', $bankAccount->getMorphClass())
        ->where('auditable_id', $bankAccount->id)
        ->latest('id')
        ->first();

    expect($auditTrail)->not->toBeNull()
        ->and($auditTrail->old_values['bank_name'])->toBe('Old Bank')
        ->and($auditTrail->new_values['bank_name'])->toBe('New Bank')
        ->and($auditTrail->metadata['changed_by'])->toBe($actor->id);
});

it('protects audit trail records with read-only authorization', function () {
    $guest = User::factory()->create();
    $auditor = User::factory()->create();
    Permission::query()->create([
        'name' => 'audit_trail.view',
        'guard_name' => 'web',
    ]);
    $auditor->givePermissionTo('audit_trail.view');

    $auditTrail = AuditTrail::query()->create([
        'event_type' => 'posting',
        'action' => 'posted',
        'document_type' => 'TEST',
        'document_no' => 'AUD-SEC-001',
        'description' => 'Security audit fixture',
        'occurred_at' => now(),
    ]);

    $this->actingAs($guest);
    expect(Gate::allows('viewAny', AuditTrail::class))->toBeFalse()
        ->and(Gate::allows('view', $auditTrail))->toBeFalse()
        ->and(Gate::allows('update', $auditTrail))->toBeFalse()
        ->and(Gate::allows('delete', $auditTrail))->toBeFalse()
        ->and(AuditTrailResource::canViewAny())->toBeFalse();

    $this->actingAs($auditor);
    expect(Gate::allows('viewAny', AuditTrail::class))->toBeTrue()
        ->and(Gate::allows('view', $auditTrail))->toBeTrue()
        ->and(Gate::allows('update', $auditTrail))->toBeFalse()
        ->and(Gate::allows('delete', $auditTrail))->toBeFalse()
        ->and(AuditTrailResource::canViewAny())->toBeTrue();
});
