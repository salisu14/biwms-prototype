<?php

namespace App\Listeners;

use App\Events\FixedAssetPosted;
use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Events\PayrollPosted;
use App\Events\PayrollSalaryPaid;
use App\Events\ProductionOrderStatusChanged;
use App\Models\PaymentApplication;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

class RecordAuditTrailForDomainEvent
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function handlePaymentApplied(PaymentApplied $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $application = $event->application->loadMissing('payment');
            $payment = $application->payment;

            $this->auditTrailService->recordPayment(
                auditable: $application,
                action: 'applied',
                userId: $application->applied_by,
                documentType: $application->document_type,
                documentNo: $application->document_number,
                metadata: $this->paymentApplicationMetadata($application),
                description: "Payment {$payment?->payment_number} applied to {$application->document_type} {$application->document_number}",
            );
        });
    }

    public function handlePaymentUnapplied(PaymentUnapplied $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $application = $event->application->loadMissing('payment');
            $payment = $application->payment;

            $this->auditTrailService->recordGeneric(
                eventType: 'reversal',
                action: 'payment_unapplied',
                auditable: $application,
                documentType: $application->document_type,
                documentNo: $application->document_number,
                source: $payment,
                userId: $application->reversed_by,
                description: "Payment {$payment?->payment_number} unapplied from {$application->document_type} {$application->document_number}",
                metadata: $this->paymentApplicationMetadata($application),
            );
        });
    }

    public function handlePayrollPosted(PayrollPosted $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $this->auditTrailService->recordGeneric(
                eventType: 'payroll',
                action: 'posted',
                auditable: $event->document,
                documentType: 'PAYROLL_DOCUMENT',
                documentNo: $event->document->document_number,
                description: "Payroll document {$event->document->document_number} posted",
                metadata: [
                    'total_earnings' => $event->document->total_earnings,
                    'total_deductions' => $event->document->total_deductions,
                    'total_net_pay' => $event->document->total_net_pay,
                    'status' => $event->document->status?->value ?? $event->document->status,
                ],
            );
        });
    }

    public function handlePayrollSalaryPaid(PayrollSalaryPaid $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $this->auditTrailService->recordGeneric(
                eventType: 'payroll',
                action: 'salary_paid',
                auditable: $event->document,
                documentType: 'PAYROLL_DOCUMENT',
                documentNo: $event->document->document_number,
                source: $event->bankLedgerEntry,
                description: "Payroll salaries paid for {$event->document->document_number}",
                metadata: [
                    'bank_ledger_entry_id' => $event->bankLedgerEntry->getKey(),
                    'bank_ledger_entry_number' => $event->bankLedgerEntry->entry_number,
                    'amount' => $event->bankLedgerEntry->amount,
                    'bank_account_id' => $event->bankLedgerEntry->bank_account_id,
                ],
            );
        });
    }

    public function handleFixedAssetPosted(FixedAssetPosted $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $entry = $event->entry->loadMissing('fixedAsset');
            $postingType = $entry->fa_posting_type?->value ?? $entry->fa_posting_type;

            $this->auditTrailService->recordGeneric(
                eventType: 'fixed_asset',
                action: 'posted',
                auditable: $entry->fixedAsset,
                documentType: $entry->document_type,
                documentNo: $entry->document_no,
                userId: $entry->created_by,
                description: "Fixed asset {$postingType} posted for {$entry->document_no}",
                metadata: [
                    'entry_no' => $entry->entry_no,
                    'fixed_asset_id' => $entry->fixed_asset_id,
                    'depreciation_book_id' => $entry->depreciation_book_id,
                    'fa_posting_type' => $postingType,
                    'amount' => $entry->amount,
                    'amount_lcy' => $entry->amount_lcy,
                ],
            );
        });
    }

    public function handleProductionOrderStatusChanged(ProductionOrderStatusChanged $event): void
    {
        $this->recordSafely(function () use ($event): void {
            $this->auditTrailService->recordGeneric(
                eventType: 'manufacturing',
                action: 'status_changed',
                auditable: $event->order,
                documentType: 'PRODUCTION_ORDER',
                documentNo: $event->order->document_number,
                userId: auth()->id(),
                description: "Production order {$event->order->document_number} status changed",
                oldValues: ['status' => $event->oldStatus->value],
                newValues: ['status' => $event->newStatus->value],
                metadata: [
                    'item_id' => $event->order->item_id,
                    'quantity_base' => $event->order->quantity_base,
                    'unit_of_measure_code' => $event->order->unit_of_measure_code,
                ],
            );
        });
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->recordPermissionEvent($event->model, 'permission_granted', $this->permissionNames($event->permissionsOrIds), 'permissions');
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->recordPermissionEvent($event->model, 'permission_revoked', $this->permissionNames($event->permissionsOrIds), 'permissions');
    }

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->recordPermissionEvent($event->model, 'role_assigned', $this->roleNames($event->rolesOrIds), 'roles');
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->recordPermissionEvent($event->model, 'role_removed', $this->roleNames($event->rolesOrIds), 'roles');
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentApplicationMetadata(PaymentApplication $application): array
    {
        return [
            'payment_id' => $application->payment_id,
            'payment_number' => $application->payment?->payment_number,
            'amount_applied' => $application->amount_applied,
            'discount_applied' => $application->discount_applied,
            'write_off_amount' => $application->write_off_amount,
            'document_remaining_before' => $application->document_remaining_before,
            'document_remaining_after' => $application->document_remaining_after,
        ];
    }

    /**
     * @param  array<int, string>  $names
     */
    private function recordPermissionEvent(Model $model, string $action, array $names, string $metadataKey): void
    {
        $this->recordSafely(function () use ($action, $metadataKey, $model, $names): void {
            $this->auditTrailService->recordPermissionChange(
                auditable: $model,
                action: $action,
                userId: auth()->id(),
                newValues: [$metadataKey => $names],
                metadata: [
                    'model_type' => $model::class,
                    'model_id' => $model->getKey(),
                    $metadataKey => $names,
                ],
            );
        });
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(mixed $permissionsOrIds): array
    {
        return $this->namesForPermissionPayload($permissionsOrIds, SpatiePermission::class);
    }

    /**
     * @return array<int, string>
     */
    private function roleNames(mixed $rolesOrIds): array
    {
        return $this->namesForPermissionPayload($rolesOrIds, SpatieRole::class);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private function namesForPermissionPayload(mixed $payload, string $modelClass): array
    {
        $items = $payload instanceof Collection ? $payload->all() : Arr::wrap($payload);
        $names = [];
        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof Model) {
                $names[] = (string) ($item->getAttribute('name') ?? $item->getKey());

                continue;
            }

            if (is_numeric($item)) {
                $ids[] = (int) $item;

                continue;
            }

            if ($item !== null) {
                $names[] = (string) $item;
            }
        }

        if ($ids !== []) {
            $names = [
                ...$names,
                ...$modelClass::query()->whereKey($ids)->pluck('name')->all(),
            ];
        }

        return array_values(array_unique($names));
    }

    private function recordSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::warning('Unable to record audit trail event.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
