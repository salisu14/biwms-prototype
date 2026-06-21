<?php

namespace App\Observers;

use App\Models\NumberSeries;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SensitiveSetupAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $this->attributesForAudit($model));
    }

    public function updated(Model $model): void
    {
        $newValues = Arr::except($model->getChanges(), ['created_at', 'updated_at']);

        if ($newValues === [] || $this->isOperationalNumberSeriesIncrement($model, $newValues)) {
            return;
        }

        $oldValues = collect($newValues)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $model->getOriginal($key)])
            ->all();

        $this->record($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $this->attributesForAudit($model), null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        app(AuditTrailService::class)->recordSetupChange(
            auditable: $model,
            action: $action,
            userId: auth()->id(),
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'changed_by' => auth()->id(),
                'changed_at' => now()->toISOString(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesForAudit(Model $model): array
    {
        return Arr::except($model->getAttributes(), ['created_at', 'updated_at']);
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function isOperationalNumberSeriesIncrement(Model $model, array $newValues): bool
    {
        return $model::class === NumberSeries::class
            && array_keys($newValues) === ['current_number'];
    }
}
