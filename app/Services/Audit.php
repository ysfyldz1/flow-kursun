<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Her önemli işlem buraya yazılır: kim, neyi, hangi kanaldan (web / portal / ai / api) değiştirdi.
 * İleride AI'ın "geçen hafta X projesinde ne oldu" sorusuna da bu tablo cevap verir.
 */
final class Audit
{
    public static function log(string $action, ?string $subjectType = null, ?int $subjectId = null, array $changes = [], string $channel = 'web'): void
    {
        DB::insert('audit_log', [
            'user_id'      => Auth::id(),
            'channel'      => $channel,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'changes'      => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
