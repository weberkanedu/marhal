<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function log(string $action, ?Model $subject = null, ?array $changes = null, ?User $user = null): AuditLog
    {
        $user ??= $this->request->user();

        return AuditLog::create([
            'tenant_id' => $subject?->getAttribute('tenant_id') ?? $this->tenant->id() ?? $user?->tenant_id,
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255) ?: null,
        ]);
    }
}
