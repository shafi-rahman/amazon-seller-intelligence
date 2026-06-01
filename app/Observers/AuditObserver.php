<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->log('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) {
            return;
        }

        $old = array_intersect_key($model->getOriginal(), $dirty);
        $this->log('updated', $model, $this->sanitize($old), $this->sanitize($dirty));
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, $this->sanitize($model->getAttributes()), null);
    }

    private function log(string $event, Model $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => $event,
            'entity_type' => get_class($model),
            'entity_id'   => $model->getKey(),
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => request()?->ip(),
            'user_agent'  => request()?->userAgent(),
        ]);
    }

    private function sanitize(array $attrs): array
    {
        // Strip sensitive fields from audit values
        return array_diff_key($attrs, array_flip(['password', 'remember_token']));
    }
}
