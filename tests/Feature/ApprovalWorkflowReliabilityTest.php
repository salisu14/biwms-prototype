<?php

use App\Enums\ApprovalStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\AuditTrail;
use App\Models\Customer;
use App\Models\InventoryAdjustmentJournal;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\Workflow\DocumentApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('enforces sales invoice submit approve reject reopen workflow with audit logs', function () {
    $user = User::factory()->create();
    grantWorkflowPermission($user, 'sales.invoice.submit');
    grantWorkflowPermission($user, 'sales.invoice.approve');
    grantWorkflowPermission($user, 'sales.invoice.reject');
    grantWorkflowPermission($user, 'sales.invoice.reopen');

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-WF-001',
        'customer_id' => Customer::factory()->create()->id,
        'status' => ApprovalStatus::DRAFT,
        'invoice_date' => now(),
        'due_date' => now()->addDays(7),
        'currency_code' => 'NGN',
    ]);

    expect(Gate::forUser(User::factory()->create())->allows('approve', $invoice))->toBeFalse();

    $workflow = app(DocumentApprovalWorkflowService::class);
    $submitted = $workflow->submit($invoice, $user->id);
    expect($submitted->status)->toBe(ApprovalStatus::PENDING);

    $rejected = $workflow->reject($submitted, $user->id);
    expect($rejected->status)->toBe(ApprovalStatus::REJECTED);

    $resubmitted = $workflow->submit($rejected, $user->id);
    $approved = $workflow->approve($resubmitted, $user->id);

    expect($approved->status)->toBe(ApprovalStatus::APPROVED)
        ->and($approved->approved_by)->toBe($user->id)
        ->and(AuditTrail::query()->where('action', 'approved')->where('auditable_type', $approved->getMorphClass())->exists())->toBeTrue();
});

it('blocks payment posting until approval and keeps failed posting immutable', function () {
    $user = User::factory()->create();
    grantWorkflowPermission($user, 'finance.payment.post');

    $payment = Payment::factory()->customerReceipt()->create([
        'payment_amount' => 100,
        'payment_amount_lcy' => 100,
        'unapplied_amount' => 100,
        'status' => 'PENDING',
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(Exception::class, 'Only approved payments can be posted.');

    expect($payment->fresh()->status)->toBe('PENDING');

    app(DocumentApprovalWorkflowService::class)->submit($payment, $user->id);
    $approved = app(DocumentApprovalWorkflowService::class)->approve($payment->fresh(), $user->id);

    expect($approved->status)->toBe('APPROVED')
        ->and(AuditTrail::query()->where('action', 'approved')->where('auditable_type', $payment->getMorphClass())->exists())->toBeTrue();
});

it('moves production order through submitted approved states and blocks posted reopen', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $order = ProductionOrder::query()->create([
        'document_number' => 'PROD-WF-001',
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => Item::factory()->create()->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'created_by' => $user->id,
    ]);

    $workflow = app(DocumentApprovalWorkflowService::class);

    $submitted = $workflow->submit($order, $user->id);
    expect($submitted->status)->toBe(ProductionOrderStatus::FIRM_PLANNED);

    $approved = $workflow->approve($submitted, $user->id);
    expect($approved->status)->toBe(ProductionOrderStatus::RELEASED);

    $approved->forceFill([
        'posted' => true,
        'posted_at' => now(),
    ])->save();

    expect(fn () => $workflow->reopen($approved->fresh(), $user->id))
        ->toThrow(RuntimeException::class, 'Posted documents are immutable.');
});

it('moves inventory adjustment through submit approve and creates audit logs', function () {
    $user = User::factory()->create();
    $journal = InventoryAdjustmentJournal::query()->create([
        'journal_batch_name' => 'IA-WF-001',
        'posting_date' => now(),
        'document_date' => now(),
        'status' => 'Open',
    ]);

    $workflow = app(DocumentApprovalWorkflowService::class);

    $submitted = $workflow->submit($journal, $user->id);
    expect($submitted->status)->toBe('Submitted');

    $approved = $workflow->approve($submitted, $user->id);
    expect($approved->status)->toBe('Released')
        ->and(AuditTrail::query()->where('action', 'approved')->where('auditable_type', $journal->getMorphClass())->exists())->toBeTrue();
});

function grantWorkflowPermission(User $user, string $permission): void
{
    Permission::query()->firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}
