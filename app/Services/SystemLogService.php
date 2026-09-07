<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;

class SystemLogService
{
    public function log(
        string $action,
        string $module,
        ?string $description = null,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): SystemLog {
        return SystemLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            // 'entity_id'=>$entity?->id,
            // 'entity_type'=>$entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'entity_type' => $entity ? get_class($entity) : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
    // public function __construct()
    // {

    // }
}
