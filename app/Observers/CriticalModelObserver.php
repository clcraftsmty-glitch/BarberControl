<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class CriticalModelObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record('created', $this->description($model, 'creado'), $model, after: $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        foreach (['updated_at', 'last_login_at', 'last_login_ip', 'last_two_factor_at', 'remember_token'] as $ignored) {
            unset($changes[$ignored]);
        }
        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getOriginal($key);
        }

        $this->audit->record('updated', $this->description($model, 'actualizado'), $model, $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record('deleted', $this->description($model, 'eliminado'), $model, $model->getOriginal());
    }

    private function description(Model $model, string $action): string
    {
        return class_basename($model).' #'.$model->getKey().' '.$action;
    }
}
