<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Bir avukatın bir projedeki saatlik ücretini bulur. Öncelik:
 *   1. projede avukata özel  2. proje  3. müvekkilde avukata özel  4. müvekkil  5. avukatın varsayılanı
 * Ücret her zaman projenin para biriminde döner; avukatın varsayılanı başka para
 * birimindeyse kullanılmaz (kur çevrimi yapılmaz).
 */
final class RateService
{
    /** @return array{rate: ?float, currency: string, source: ?string} */
    public static function resolve(int $projectId, int $userId): array
    {
        $p = DB::one(
            'SELECT p.currency, p.hourly_rate AS project_rate, p.client_id,
                    pm.hourly_rate AS member_rate,
                    c.hourly_rate AS client_rate, c.currency AS client_currency,
                    cur.hourly_rate AS client_user_rate,
                    u.hourly_rate AS user_rate, u.rate_currency AS user_currency
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             JOIN users u ON u.id = ?
             LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = u.id
             LEFT JOIN client_user_rates cur ON cur.client_id = c.id AND cur.user_id = u.id
             WHERE p.id = ?',
            [$userId, $projectId]
        );
        if (!$p) {
            return ['rate' => null, 'currency' => 'TRY', 'source' => null];
        }

        $sameAsClient = $p['currency'] === $p['client_currency'];
        $candidates = [
            'project_member' => $p['member_rate'],
            'project'        => $p['project_rate'],
            'client_member'  => $sameAsClient ? $p['client_user_rate'] : null,
            'client'         => $sameAsClient ? $p['client_rate'] : null,
            'user'           => $p['user_currency'] === $p['currency'] ? $p['user_rate'] : null,
        ];
        foreach ($candidates as $source => $rate) {
            if ($rate !== null) {
                return ['rate' => (float) $rate, 'currency' => $p['currency'], 'source' => $source];
            }
        }
        return ['rate' => null, 'currency' => $p['currency'], 'source' => null];
    }
}
