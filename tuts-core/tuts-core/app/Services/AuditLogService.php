<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an admin action to the audit logs.
     *
     * @param string $action
     * @param string|null $targetType
     * @param mixed $targetId
     * @param array|null $metadata
     * @return AuditLog
     */
    public static function log(string $action, ?string $targetType = null, $targetId = null, ?array $metadata = null): AuditLog
    {
        $actorId = Auth::id();
        $ip = Request::ip();
        $userAgent = Request::header('User-Agent');

        return AuditLog::create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (string) $targetId : null,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
