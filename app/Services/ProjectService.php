<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

final class ProjectService
{
    public const TYPES = ['litigation', 'advisory', 'transaction', 'other'];
    public const STATUSES = ['open', 'on_hold', 'closed'];
    public const BILLING = ['hourly', 'fixed', 'retainer', 'non_billable'];

    public static function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.number LIKE ? OR c.name LIKE ? OR p.case_number LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $status = $filters['status'] ?? 'open';
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'p.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }
        if (!empty($filters['mine'])) {
            $where[] = '(p.responsible_user_id = ? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?))';
            array_push($params, Auth::id(), Auth::id());
        }

        return DB::all(
            'SELECT p.*, c.name AS client_name, c.code AS client_code, u.name AS responsible_name
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.responsible_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.opened_at DESC, p.id DESC',
            $params
        );
    }

    public static function forClient(int $clientId): array
    {
        return self::list(['client_id' => $clientId, 'status' => 'all']);
    }

    public static function countOpen(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM projects WHERE status = 'open'");
    }

    public static function find(int $id): ?array
    {
        $p = DB::one(
            'SELECT p.*, c.name AS client_name, c.code AS client_code, u.name AS responsible_name
             FROM projects p JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.responsible_user_id
             WHERE p.id = ?',
            [$id]
        );
        if ($p) {
            $p['members'] = DB::all(
                'SELECT m.user_id, m.hourly_rate, u.name, u.title FROM project_members m
                 JOIN users u ON u.id = m.user_id WHERE m.project_id = ? ORDER BY u.name',
                [$id]
            );
        }
        return $p;
    }

    public static function create(array $input, string $channel = 'web'): int
    {
        [$data, $members] = self::validate($input, null);

        return DB::transaction(function () use ($data, $members, $channel) {
            $client = DB::one('SELECT code FROM clients WHERE id = ? FOR UPDATE', [$data['client_id']]);
            $data['number'] = sprintf('%s/%03d', $client['code'], Counter::next('project.' . $data['client_id']));
            $data['created_by'] = Auth::id();
            $id = DB::insert('projects', $data);
            self::syncMembers($id, $members, $data['responsible_user_id']);
            Audit::log('project.created', 'project', $id, ['number' => $data['number'], 'name' => $data['name']], $channel);
            return $id;
        });
    }

    public static function update(int $id, array $input, string $channel = 'web'): void
    {
        $current = self::find($id);
        [$data, $members] = self::validate($input, $current);

        DB::transaction(function () use ($id, $data, $members, $current, $channel) {
            // Kapanış tarihi durumla birlikte yönetilir
            if ($data['status'] === 'closed' && $current['status'] !== 'closed') {
                $data['closed_at'] = date('Y-m-d');
            } elseif ($data['status'] !== 'closed') {
                $data['closed_at'] = null;
            }
            DB::update('projects', $data, 'id = ?', [$id]);
            self::syncMembers($id, $members, $data['responsible_user_id']);
            Audit::log('project.updated', 'project', $id, $data, $channel);
        });
    }

    /** $members: [user_id => rate|null]. Ücret yetkisi yoksa mevcut ücretler korunur. */
    private static function syncMembers(int $id, array $members, ?int $responsible): void
    {
        if ($responsible && !array_key_exists($responsible, $members)) {
            $members[$responsible] = null;
        }
        $existing = array_column(DB::all('SELECT user_id, hourly_rate FROM project_members WHERE project_id = ?', [$id]), 'hourly_rate', 'user_id');
        $canRates = Auth::can('rates.view');

        DB::query('DELETE FROM project_members WHERE project_id = ?', [$id]);
        foreach ($members as $userId => $rate) {
            DB::insert('project_members', [
                'project_id'  => $id,
                'user_id'     => $userId,
                'hourly_rate' => $canRates ? $rate : ($existing[$userId] ?? null),
            ]);
        }
    }

    private static function validate(array $in, ?array $current): array
    {
        $errors = [];
        $str = fn (string $k) => trim((string) ($in[$k] ?? '')) ?: null;
        $canRates = Auth::can('rates.view');

        // Müvekkil proje açıldıktan sonra değiştirilemez (numara müvekkil kodundan türer)
        $clientId = $current ? (int) $current['client_id'] : (int) ($in['client_id'] ?? 0);
        $client = DB::one('SELECT id, currency, is_active FROM clients WHERE id = ?', [$clientId]);

        $data = [
            'client_id'           => $clientId,
            'name'                => (string) $str('name'),
            'type'                => (string) ($in['type'] ?? 'advisory'),
            'status'              => (string) ($in['status'] ?? 'open'),
            'responsible_user_id' => (int) ($in['responsible_user_id'] ?? 0) ?: null,
            'billing_type'        => (string) ($in['billing_type'] ?? 'hourly'),
            'currency'            => (string) ($in['currency'] ?? ($client['currency'] ?? 'TRY')),
            'hourly_rate'         => parse_money($in['hourly_rate'] ?? ''),
            'fixed_fee'           => parse_money($in['fixed_fee'] ?? ''),
            'retainer_amount'     => parse_money($in['retainer_amount'] ?? ''),
            'budget_hours'        => parse_money($in['budget_hours'] ?? ''),
            'court'               => $str('court'),
            'case_number'         => $str('case_number'),
            'description'         => $str('description'),
            'opened_at'           => (string) ($in['opened_at'] ?? '') ?: date('Y-m-d'),
        ];

        if (!$client || (!$current && !$client['is_active'])) {
            $errors['client_id'] = __('validation.required');
        }
        if ($data['name'] === '') {
            $errors['name'] = __('validation.required');
        }
        foreach (['type' => self::TYPES, 'status' => self::STATUSES, 'billing_type' => self::BILLING, 'currency' => UserService::CURRENCIES] as $k => $allowed) {
            if (!in_array($data[$k], $allowed, true)) {
                $errors[$k] = __('validation.required');
            }
        }
        if ($data['responsible_user_id'] && !DB::value('SELECT id FROM users WHERE id = ? AND is_active = 1', [$data['responsible_user_id']])) {
            $errors['responsible_user_id'] = __('validation.required');
        }
        foreach (['hourly_rate', 'fixed_fee', 'retainer_amount', 'budget_hours'] as $k) {
            if ($data[$k] === false) {
                $errors[$k] = __('validation.number');
            }
        }
        $d = \DateTime::createFromFormat('Y-m-d', $data['opened_at']);
        if (!$d || $d->format('Y-m-d') !== $data['opened_at']) {
            $errors['opened_at'] = __('validation.date');
        }
        if ($canRates && $data['billing_type'] === 'fixed' && $data['fixed_fee'] === null) {
            $errors['fixed_fee'] = __('validation.required');
        }
        if ($canRates && $data['billing_type'] === 'retainer' && $data['retainer_amount'] === null) {
            $errors['retainer_amount'] = __('validation.required');
        }

        // Ekip: seçili kullanıcılar ve (yetki varsa) projeye özel ücretleri
        $members = [];
        foreach ((array) ($in['members'] ?? []) as $userId) {
            $userId = (int) $userId;
            if (!DB::value('SELECT id FROM users WHERE id = ?', [$userId])) {
                continue;
            }
            $rate = parse_money($in['member_rates'][$userId] ?? '');
            if ($rate === false) {
                $errors["member_rates.$userId"] = __('validation.number');
            }
            $members[$userId] = $rate ?: null;
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        // Ücret yetkisi olmayan ücret alanlarını değiştiremez; para birimi de ücretlere bağlı olduğu için kilitli
        if (!$canRates) {
            unset($data['hourly_rate'], $data['fixed_fee'], $data['retainer_amount'], $data['billing_type'], $data['currency']);
            if (!$current) {
                $data['currency'] = $client['currency'];
            }
        }
        return [$data, $members];
    }
}
