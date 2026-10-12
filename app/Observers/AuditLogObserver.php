<?php

namespace App\Observers;

use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogObserver
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created', [
            'fields' => array_values(array_diff(
                array_keys($model->getAttributes()),
                ['password', 'remember_token']
            )),
        ]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $resource = $model instanceof Partner ? 'partner' : 'user';
        $isStatusChange = array_key_exists('status', $changes);
        $meta = $isStatusChange
            ? [
                'old_status' => $model->getOriginal('status'),
                'new_status' => $changes['status'],
            ]
            : ['fields' => array_keys($changes)];

        $this->record(
            $model,
            $isStatusChange ? 'status_changed' : 'updated',
            $meta,
            $resource
        );
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(
        Model $model,
        string $event,
        array $meta = [],
        ?string $resource = null
    ): void {
        $actor = Auth::user();

        if (
            $actor === null
            || $actor->partner_id === null
            || ! $actor->hasAnyRole(['partner_admin', 'partner_recruiter'])
            || ! request()->is('api/v1/partner/*')
        ) {
            return;
        }

        $resource ??= $model instanceof Partner ? 'partner' : 'user';
        $partnerId = $model instanceof Partner
            ? $model->getKey()
            : ($model->partner_id ?? $actor->partner_id);

        $this->auditLogService->log(
            action: "{$resource}.{$event}",
            subject: $model,
            meta: $meta,
            partnerId: (int) $partnerId,
        );
    }
}