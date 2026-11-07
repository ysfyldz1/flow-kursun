<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use DateTime;

/**
 * Görevler: başlık, isteğe bağlı açıklama/proje/son tarih, öncelik (normal/acil), birden fazla atanan.
 * Durum yalnız açık/tamamlandı. Atanınca atanana, tamamlanınca görevi verene e-posta gider.
 * Görevi veren ya da tasks.all düzenler/siler; atananlar tamamlandı işaretleyebilir.
 */
final class TaskService
{
    public const PRIORITIES = ['normal', 'urgent'];

    private const SELECT = "SELECT t.*, p.number AS project_number, p.name AS project_name, c.name AS client_name,
                                   cu.name AS creator_name, du.name AS done_by_name,
                                   (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ')
                                      FROM task_assignees a JOIN users u ON u.id = a.user_id WHERE a.task_id = t.id) AS assignee_names,
                                   (SELECT GROUP_CONCAT(a.user_id) FROM task_assignees a WHERE a.task_id = t.id) AS assignee_ids
                            FROM tasks t
                            LEFT JOIN projects p ON p.id = t.project_id
                            LEFT JOIN clients c ON c.id = p.client_id
                            LEFT JOIN users cu ON cu.id = t.created_by
                            LEFT JOIN users du ON du.id = t.done_by";

    /** Açık görevlerde sıra: gecikenler ve acil olanlar önde, sonra son tarihe göre; tarihsizler sonda. */
    private const ORDER_OPEN = " ORDER BY (t.due_date IS NOT NULL AND t.due_date < CURDATE()) DESC, t.priority = 'urgent' DESC,
                                 t.due_date IS NULL, t.due_date, t.id DESC";

    public static function find(int $id): ?array
    {
        $t = DB::one(self::SELECT . ' WHERE t.id = ?', [$id]);
        if ($t) {
            $t['assignees'] = array_map('intval', array_filter(explode(',', (string) $t['assignee_ids'])));
        }
        return $t;
    }

    /** scope: mine (bana atanan), given (benim verdiğim), all (tasks.all); state: open | done */
    public static function list(string $scope, string $state = 'open'): array
    {
        $where = [$state === 'done' ? 't.done_at IS NOT NULL' : 't.done_at IS NULL'];
        $params = [];
        if ($scope === 'given') {
            $where[] = 't.created_by = ?';
            $params[] = Auth::id();
        } elseif ($scope !== 'all' || !Auth::can('tasks.all')) {
            $where[] = 'EXISTS (SELECT 1 FROM task_assignees a WHERE a.task_id = t.id AND a.user_id = ?)';
            $params[] = Auth::id();
        }
        $order = $state === 'done' ? ' ORDER BY t.done_at DESC LIMIT 200' : self::ORDER_OPEN;
        return DB::all(self::SELECT . ' WHERE ' . implode(' AND ', $where) . $order, $params);
    }

    public static function forProject(int $projectId): array
    {
        return DB::all(self::SELECT . ' WHERE t.project_id = ? AND (t.done_at IS NULL OR t.done_at > NOW() - INTERVAL 14 DAY)
                                         ORDER BY t.done_at IS NOT NULL, t.due_date IS NULL, t.due_date, t.id DESC', [$projectId]);
    }

    public static function countOpenMine(): int
    {
        return (int) DB::value(
            'SELECT COUNT(*) FROM tasks t JOIN task_assignees a ON a.task_id = t.id WHERE a.user_id = ? AND t.done_at IS NULL',
            [Auth::id()]
        );
    }

    public static function isOverdue(array $t): bool
    {
        return !$t['done_at'] && $t['due_date'] && $t['due_date'] < date('Y-m-d');
    }

    public static function canEdit(array $t): bool
    {
        return (int) $t['created_by'] === Auth::id() || Auth::can('tasks.all');
    }

    public static function canComplete(array $t): bool
    {
        return self::canEdit($t) || in_array(Auth::id(), array_map('intval', explode(',', (string) $t['assignee_ids'])), true);
    }

    public static function create(array $in, string $channel = 'web'): int
    {
        [$data, $assignees] = self::validate($in);
        $id = DB::transaction(function () use ($data, $assignees) {
            $id = DB::insert('tasks', $data + ['created_by' => Auth::id()]);
            foreach ($assignees as $uid) {
                DB::insert('task_assignees', ['task_id' => $id, 'user_id' => $uid]);
            }
            return $id;
        });
        Audit::log('task.created', 'task', $id, ['title' => $data['title'], 'assignees' => $assignees], $channel);
        self::notifyAssigned($id, $assignees);
        return $id;
    }

    public static function update(int $id, array $in, string $channel = 'web'): void
    {
        $t = self::find($id);
        if (!$t || !self::canEdit($t)) {
            throw new ValidationException(['task' => __('tasks.not_allowed')]);
        }
        [$data, $assignees] = self::validate($in);
        DB::transaction(function () use ($id, $data, $assignees) {
            DB::update('tasks', $data, 'id = ?', [$id]);
            DB::query('DELETE FROM task_assignees WHERE task_id = ?', [$id]);
            foreach ($assignees as $uid) {
                DB::insert('task_assignees', ['task_id' => $id, 'user_id' => $uid]);
            }
        });
        Audit::log('task.updated', 'task', $id, $data + ['assignees' => $assignees], $channel);
        self::notifyAssigned($id, array_diff($assignees, $t['assignees']));   // yalnız yeni eklenenlere
    }

    public static function toggleDone(int $id, string $channel = 'web'): bool
    {
        $t = self::find($id);
        if (!$t || !self::canComplete($t)) {
            throw new ValidationException(['task' => __('tasks.not_allowed')]);
        }
        $done = !$t['done_at'];
        DB::update('tasks', ['done_at' => $done ? date('Y-m-d H:i:s') : null, 'done_by' => $done ? Auth::id() : null], 'id = ?', [$id]);
        Audit::log($done ? 'task.done' : 'task.reopened', 'task', $id, [], $channel);
        if ($done && $t['created_by'] && (int) $t['created_by'] !== Auth::id()) {
            $creator = DB::one('SELECT name, email, locale FROM users WHERE id = ? AND is_active = 1', [$t['created_by']]);
            if ($creator) {
                Mailer::sendView($creator['email'], $creator['locale'], 'task_done', self::mailData(self::find($id), $creator['name']));
            }
        }
        return $done;
    }

    public static function delete(int $id, string $channel = 'web'): void
    {
        $t = self::find($id);
        if (!$t || !self::canEdit($t)) {
            throw new ValidationException(['task' => __('tasks.not_allowed')]);
        }
        DB::query('DELETE FROM tasks WHERE id = ?', [$id]);
        Audit::log('task.deleted', 'task', $id, ['title' => $t['title']], $channel);
    }

    /** Son tarihte (yoksa bugün) onaylı izinde olan atananların adları; kayıt engellenmez, uyarı gösterilir. */
    public static function assigneesOnLeave(array $t): array
    {
        $date = $t['due_date'] ?: date('Y-m-d');
        $names = [];
        foreach ($t['assignees'] as $uid) {
            if (LeaveService::isOnLeave($uid, $date)) {
                $names[] = DB::value('SELECT name FROM users WHERE id = ?', [$uid]);
            }
        }
        return $names;
    }

    private static function validate(array $in): array
    {
        $errors = [];
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = __('validation.required');
        }
        $projectId = (int) ($in['project_id'] ?? 0) ?: null;
        if ($projectId && (!DB::value('SELECT id FROM projects WHERE id = ?', [$projectId]) || (!Access::canSeeProject($projectId) && (int) ($current['project_id'] ?? 0) !== $projectId))) {
            $errors['project_id'] = __('validation.required');
        }
        $due = (string) ($in['due_date'] ?? '') ?: null;
        if ($due !== null && (!($d = DateTime::createFromFormat('!Y-m-d', $due)) || $d->format('Y-m-d') !== $due)) {
            $errors['due_date'] = __('validation.date');
        }
        $priority = in_array($in['priority'] ?? '', self::PRIORITIES, true) ? $in['priority'] : 'normal';
        $assignees = array_values(array_unique(array_filter(array_map('intval', (array) ($in['assignees'] ?? [])))));
        if ($assignees) {
            $assignees = array_map('intval', array_column(DB::all(
                'SELECT id FROM users WHERE is_active = 1 AND id IN (' . implode(',', $assignees) . ')'
            ), 'id'));
        }
        if (!$assignees) {
            $errors['assignees'] = __('tasks.assignees_required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return [[
            'title'       => mb_substr($title, 0, 255),
            'description' => trim((string) ($in['description'] ?? '')) ?: null,
            'project_id'  => $projectId,
            'due_date'    => $due,
            'priority'    => $priority,
        ], $assignees];
    }

    private static function notifyAssigned(int $id, array $userIds): void
    {
        $userIds = array_diff($userIds, [Auth::id()]);   // kendine verdiği görev için e-posta yok
        if (!$userIds) {
            return;
        }
        $t = self::find($id);
        foreach (DB::all('SELECT name, email, locale FROM users WHERE is_active = 1 AND id IN (' . implode(',', array_map('intval', $userIds)) . ')') as $u) {
            Mailer::sendView($u['email'], $u['locale'], 'task_assigned', self::mailData($t, $u['name']));
        }
    }

    private static function mailData(array $t, string $recipient): array
    {
        return [
            'name'     => $recipient,
            'title'    => $t['title'],
            'description' => $t['description'],
            'project'  => $t['project_id'] ? $t['project_number'] . ' · ' . $t['project_name'] : null,
            'due'      => $t['due_date'],
            'urgent'   => $t['priority'] === 'urgent',
            'creator'  => (string) $t['creator_name'],
            'doneBy'   => (string) $t['done_by_name'],
            'url'      => rtrim((string) config('app.url'), '/') . '/tasks/' . $t['id'],
        ];
    }
}
