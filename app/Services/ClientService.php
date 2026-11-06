<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

final class ClientService
{
    public static function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.code LIKE ? OR c.tax_id LIKE ? OR c.email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $where[] = 'c.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'c.is_active = 0';
        }
        if (in_array($filters['type'] ?? '', ['person', 'company'], true)) {
            $where[] = 'c.type = ?';
            $params[] = $filters['type'];
        }

        return DB::all(
            'SELECT c.*,
                    (SELECT name FROM client_contacts cc WHERE cc.client_id = c.id ORDER BY cc.is_primary DESC, cc.id LIMIT 1) AS contact_name
             FROM clients c
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.name',
            $params
        );
    }

    public static function countActive(): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM clients WHERE is_active = 1');
    }

    public static function find(int $id): ?array
    {
        $client = DB::one('SELECT * FROM clients WHERE id = ?', [$id]);
        if ($client) {
            $client['contacts'] = DB::all('SELECT * FROM client_contacts WHERE client_id = ? ORDER BY is_primary DESC, id', [$id]);
            $client['user_rates'] = DB::all(
                'SELECT r.user_id, r.hourly_rate, u.name FROM client_user_rates r JOIN users u ON u.id = r.user_id
                 WHERE r.client_id = ? ORDER BY u.name',
                [$id]
            );
        }
        return $client;
    }

    public static function create(array $input, string $channel = 'web'): int
    {
        [$data, $contacts, $rates] = self::validate($input);

        return DB::transaction(function () use ($data, $contacts, $rates, $channel) {
            $data['code'] = sprintf('M-%04d', Counter::next('client'));
            $data['created_by'] = Auth::id();
            $id = DB::insert('clients', $data);
            self::syncChildren($id, $contacts, $rates);
            Audit::log('client.created', 'client', $id, ['code' => $data['code'], 'name' => $data['name']], $channel);
            return $id;
        });
    }

    public static function update(int $id, array $input, string $channel = 'web'): void
    {
        [$data, $contacts, $rates] = self::validate($input);

        DB::transaction(function () use ($id, $data, $contacts, $rates, $channel) {
            DB::update('clients', $data, 'id = ?', [$id]);
            self::syncChildren($id, $contacts, $rates);
            Audit::log('client.updated', 'client', $id, $data, $channel);
        });
    }

    /** Müvekkil silinmez; projeleri ve dökümleri olduğu için pasife alınır. */
    public static function setActive(int $id, bool $active, string $channel = 'web'): void
    {
        DB::update('clients', ['is_active' => (int) $active], 'id = ?', [$id]);
        Audit::log($active ? 'client.activated' : 'client.deactivated', 'client', $id, [], $channel);
    }

    private static function syncChildren(int $id, array $contacts, array $rates): void
    {
        DB::query('DELETE FROM client_contacts WHERE client_id = ?', [$id]);
        foreach ($contacts as $c) {
            DB::insert('client_contacts', $c + ['client_id' => $id]);
        }
        DB::query('DELETE FROM client_user_rates WHERE client_id = ?', [$id]);
        foreach ($rates as $userId => $rate) {
            DB::insert('client_user_rates', ['client_id' => $id, 'user_id' => $userId, 'hourly_rate' => $rate]);
        }
    }

    private static function validate(array $in): array
    {
        $errors = [];
        $str = fn (string $k) => trim((string) ($in[$k] ?? '')) ?: null;

        $data = [
            'type'        => ($in['type'] ?? '') === 'person' ? 'person' : 'company',
            'name'        => (string) $str('name'),
            'tax_id'      => $str('tax_id') ? preg_replace('/\s+/', '', $str('tax_id')) : null,
            'tax_office'  => $str('tax_office'),
            'email'       => $str('email') ? mb_strtolower($str('email')) : null,
            'phone'       => $str('phone'),
            'address'     => $str('address'),
            'city'        => $str('city'),
            'country'     => $str('country'),
            'currency'    => (string) ($in['currency'] ?? 'TRY'),
            'locale'      => (string) ($in['locale'] ?? 'tr'),
            'hourly_rate' => parse_money($in['hourly_rate'] ?? ''),
            'notes'       => $str('notes'),
        ];

        if ($data['name'] === '') {
            $errors['name'] = __('validation.required');
        }
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email');
        }
        if ($data['tax_id']) {
            if ($data['type'] === 'person' && !self::validTckn($data['tax_id'])) {
                $errors['tax_id'] = __('clients.invalid_tckn');
            } elseif ($data['type'] === 'company' && !preg_match('/^\d{10}$/', $data['tax_id'])) {
                $errors['tax_id'] = __('clients.invalid_vkn');
            }
        }
        if (!in_array($data['currency'], UserService::CURRENCIES, true)) {
            $errors['currency'] = __('validation.required');
        }
        if (!in_array($data['locale'], config('app.locales'), true)) {
            $errors['locale'] = __('validation.required');
        }
        if ($data['hourly_rate'] === false) {
            $errors['hourly_rate'] = __('validation.number');
        }

        // İletişim kişileri: adı boş satırlar atlanır, ilk işaretli olan birincil
        $contacts = [];
        $primarySet = false;
        foreach ((array) ($in['contacts'] ?? []) as $i => $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $email = trim((string) ($c['email'] ?? '')) ?: null;
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors["contacts.$i.email"] = __('validation.email');
            }
            $primary = !$primarySet && !empty($c['is_primary']);
            $primarySet = $primarySet || $primary;
            $contacts[] = [
                'name'       => $name,
                'title'      => trim((string) ($c['title'] ?? '')) ?: null,
                'email'      => $email ? mb_strtolower($email) : null,
                'phone'      => trim((string) ($c['phone'] ?? '')) ?: null,
                'is_primary' => (int) $primary,
            ];
        }
        if ($contacts && !$primarySet) {
            $contacts[0]['is_primary'] = 1;
        }

        // Avukata özel ücretler: boş olanlar kaydedilmez
        $rates = [];
        foreach ((array) ($in['user_rates'] ?? []) as $userId => $rate) {
            $value = parse_money($rate);
            if ($value === false) {
                $errors["user_rates.$userId"] = __('validation.number');
            } elseif ($value !== null && DB::value('SELECT id FROM users WHERE id = ?', [(int) $userId])) {
                $rates[(int) $userId] = $value;
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
        return [$data, $contacts, $rates];
    }

    private static function validTckn(string $t): bool
    {
        if (!preg_match('/^[1-9]\d{10}$/', $t)) {
            return false;
        }
        $d = array_map('intval', str_split($t));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        if (((($odd * 7) - $even) % 10 + 10) % 10 !== $d[9]) {
            return false;
        }
        return array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }
}
