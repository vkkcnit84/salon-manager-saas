<?php

namespace App\Support;

use App\Support\Models\AuditLog;
use App\Tenancy\Support\TenantContext;

class Audit
{
    public static function record(string $action, ?object $subject = null, array $meta = [], ?int $userId = null): void
    {
        $tenantId = app(TenantContext::class)->tenantId();
        if ($tenantId === null) {
            return;
        }
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(), 'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject->id ?? null, 'meta' => $meta ?: null,
        ]);
    }
}
