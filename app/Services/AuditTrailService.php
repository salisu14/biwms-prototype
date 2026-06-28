<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuditTrailService
{
    /**
     * @var array<int, string>|null
     */
    private static ?array $auditTrailColumns = null;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordGeneric(
        string $eventType,
        string $action,
        ?Model $auditable = null,
        ?string $documentType = null,
        ?string $documentNo = null,
        ?Model $source = null,
        ?int $userId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Carbon $occurredAt = null,
    ): ?AuditTrail {
        if (! Schema::hasTable('audit_trails')) {
            return null;
        }

        $actorId = $userId ?? Auth::id();
        $attributes = [
            'event_type' => $eventType,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'document_type' => $documentType,
            'document_no' => $documentNo,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'actor_id' => $actorId,
            'subject_type' => $auditable?->getMorphClass(),
            'subject_id' => $auditable?->getKey(),
            'user_id' => $actorId,
            'description' => $description,
            'old_values' => $this->sanitizePayload($oldValues),
            'new_values' => $this->sanitizePayload($newValues),
            'metadata' => $this->sanitizePayload($metadata),
            'business_id' => $this->contextValue('business_id', $auditable, $metadata),
            'factory_id' => $this->contextValue('factory_id', $auditable, $metadata),
            'warehouse_id' => $this->contextValue('warehouse_id', $auditable, $metadata) ?? $this->contextValue('location_id', $auditable, $metadata),
            'ip_address' => $this->requestIp(),
            'user_agent' => $this->requestUserAgent(),
            'occurred_at' => $occurredAt ?? now(),
        ];

        return AuditTrail::query()->create($this->attributesForCurrentSchema($attributes));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordPosting(Model $auditable, ?int $userId = null, ?string $documentType = null, ?string $documentNo = null, ?array $metadata = null, ?string $description = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'posting',
            action: 'posted',
            auditable: $auditable,
            documentType: $documentType ?? $this->documentTypeFor($auditable),
            documentNo: $documentNo ?? $this->documentNoFor($auditable),
            userId: $userId,
            description: $description ?? $this->descriptionFor('Posted', $auditable),
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordReversal(Model $auditable, ?int $userId = null, ?string $documentType = null, ?string $documentNo = null, ?array $metadata = null, ?string $description = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'reversal',
            action: 'reversed',
            auditable: $auditable,
            documentType: $documentType ?? $this->documentTypeFor($auditable),
            documentNo: $documentNo ?? $this->documentNoFor($auditable),
            userId: $userId,
            description: $description ?? $this->descriptionFor('Reversed', $auditable),
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordPayment(Model $auditable, string $action, ?int $userId = null, ?string $documentType = null, ?string $documentNo = null, ?array $metadata = null, ?string $description = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'payment',
            action: $action,
            auditable: $auditable,
            documentType: $documentType ?? $this->documentTypeFor($auditable),
            documentNo: $documentNo ?? $this->documentNoFor($auditable),
            userId: $userId,
            description: $description ?? $this->descriptionFor("Payment {$action}", $auditable),
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordApproval(Model $auditable, string $action, ?int $userId = null, ?array $metadata = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'approval',
            action: $action,
            auditable: $auditable,
            documentType: $this->documentTypeFor($auditable),
            documentNo: $this->documentNoFor($auditable),
            userId: $userId,
            description: $this->descriptionFor("Approval {$action}", $auditable),
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordPermissionChange(Model $auditable, string $action, ?int $userId = null, ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'permission',
            action: $action,
            auditable: $auditable,
            documentType: class_basename($auditable),
            documentNo: $this->documentNoFor($auditable),
            userId: $userId,
            description: $this->descriptionFor("Permission {$action}", $auditable),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordSetupChange(Model $auditable, string $action, ?int $userId = null, ?array $oldValues = null, ?array $newValues = null, ?array $metadata = null): ?AuditTrail
    {
        return $this->recordGeneric(
            eventType: 'setup',
            action: $action,
            auditable: $auditable,
            documentType: class_basename($auditable),
            documentNo: $this->documentNoFor($auditable),
            userId: $userId,
            description: $this->descriptionFor("Setup {$action}", $auditable),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
        );
    }

    public function documentNoFor(Model $model): ?string
    {
        foreach (['document_number', 'document_no', 'payment_number', 'entry_number', 'entry_no', 'number', 'code', 'account_number', 'account_code', 'name'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return $model->getKey() !== null ? (string) $model->getKey() : null;
    }

    public function documentTypeFor(Model $model): string
    {
        return str(class_basename($model))->snake()->upper()->toString();
    }

    private function descriptionFor(string $prefix, Model $model): string
    {
        return trim($prefix.' '.$this->documentTypeFor($model).' '.$this->documentNoFor($model));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return collect($payload)
            ->mapWithKeys(function (mixed $value, string|int $key): array {
                $key = (string) $key;

                if (str($key)->lower()->contains(['password', 'token', 'secret', 'api_key', 'recovery_code', 'recovery_codes', 'two_factor', 'totp', 'otp', 'session'])) {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->sanitizePayload($value)];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function requestIp(): ?string
    {
        if (app()->runningInConsole() || ! request()) {
            return null;
        }

        return request()->ip();
    }

    private function requestUserAgent(): ?string
    {
        if (app()->runningInConsole() || ! request()) {
            return null;
        }

        return str((string) request()->userAgent())->limit(1000)->toString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesForCurrentSchema(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $value, string $column): bool => in_array($column, $this->auditTrailColumns(), true))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function auditTrailColumns(): array
    {
        return self::$auditTrailColumns ??= Schema::getColumnListing('audit_trails');
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function contextValue(string $key, ?Model $auditable, ?array $metadata): ?int
    {
        $value = $metadata[$key] ?? $auditable?->getAttribute($key) ?? session($key) ?? null;

        if ($key === 'business_id') {
            $value ??= session('active_business_id');
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
