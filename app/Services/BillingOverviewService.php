<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Kurşun: döküm oluşturmadan önce veriyi gösteren ekranlar.
 * Müvekkil → açık proje kartları (faturalanmamış / faturalanmış) → proje: aşama özeti + aşama sekmeleri.
 * "Faturalanmış" = kesinleşmiş dökümdeki kayıt; taslaktaki kayıt ayrıca "taslakta" sayılır.
 */
final class BillingOverviewService
{
    /** Zaman kayıtlarının durumuna göre toplamlar (saat dakika, tutar). $where t. önekli koşul. */
    private static function timeTotals(string $where, array $params): array
    {
        $r = DB::one(
            "SELECT
                COALESCE(SUM(CASE WHEN t.statement_id IS NULL THEN t.minutes END), 0) AS unbilled_minutes,
                COALESCE(SUM(CASE WHEN t.statement_id IS NULL THEN t.amount END), 0) AS unbilled_amount,
                COUNT(CASE WHEN t.statement_id IS NULL THEN 1 END) AS unbilled_count,
                COALESCE(SUM(CASE WHEN s.status = 'issued' THEN t.minutes END), 0) AS billed_minutes,
                COALESCE(SUM(CASE WHEN s.status = 'issued' THEN t.amount END), 0) AS billed_amount,
                COUNT(CASE WHEN s.status = 'draft' THEN 1 END) AS draft_count
             FROM time_entries t
             LEFT JOIN statements s ON s.id = t.statement_id
             WHERE t.is_billable = 1 AND $where",
            $params
        );
        return array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $r);
    }

    /** Müvekkilin açık (kapalı olmayan, faturalanabilir) projeleri ve faturalama durumları. */
    public static function clientProjects(int $clientId): array
    {
        $projects = DB::all(
            "SELECT p.id, p.number, p.name, p.currency, p.status, p.billing_type,
                    EXISTS (SELECT 1 FROM project_phases ph WHERE ph.project_id = p.id AND ph.is_out_of_scope = 0) AS phased
             FROM projects p
             WHERE p.client_id = ? AND p.status <> 'closed' AND p.billing_type <> 'non_billable'
             ORDER BY p.number",
            [$clientId]
        );
        foreach ($projects as &$p) {
            $p += self::timeTotals('t.project_id = ?', [$p['id']]);
            $p['state'] = self::state($p);
        }
        return $projects;
    }

    /** none: hiç kayıt yok · done: faturalanacak kayıt kalmadı · draft: kalanlar taslakta · open: faturalanmamış var */
    private static function state(array $t, bool $billedBefore = false): string
    {
        if ($t['unbilled_count'] > 0) {
            return 'open';
        }
        if ($t['draft_count'] > 0) {
            return 'draft';
        }
        return ($t['billed_minutes'] > 0 || $billedBefore) ? 'done' : 'none';
    }

    /**
     * Proje faturalama görünümü: aşama başına bedel/kesilen/kalan + zaman durumu + önceki dökümler.
     * Aşamasız kayıt varsa "none" anahtarlı bir grup eklenir.
     */
    public static function project(int $projectId): array
    {
        $groups = [];
        $no = 0;
        foreach (PhaseService::forProject($projectId) as $ph) {
            $id = (int) $ph['id'];
            $billed = StatementService::phaseBilled($id);
            $t = self::timeTotals('t.phase_id = ?', [$id]);
            $fee = $ph['fee'] !== null ? (float) $ph['fee'] : null;
            $g = [
                'key'        => (string) $id,
                'phase_id'   => $id,
                'label'      => ($ph['is_out_of_scope'] ? '' : ++$no . '. ') . $ph['name'],
                'name'       => $ph['name'],
                'oos'        => (bool) $ph['is_out_of_scope'],
                'active'     => $ph['is_active'],
                'fee'        => $fee,
                'fee_billed' => $billed['billed'],
                'extra_billed' => $billed['extra'],
                'remaining'  => $fee !== null ? round($fee - $billed['billed'], 2) : null,
                'overdue'    => $ph['is_overdue'],
                'completed'  => (bool) $ph['completed_at'],
                'statements' => self::statementsFor('l.phase_id = ?', [$id]),
            ] + $t;
            $g['state'] = self::state($t, $billed['statements'] > 0);
            $groups[] = $g;
        }
        // Aşamasız kayıtlar (aşamalı projede ayrı sekme; aşamasız projede tek grup)
        $t = self::timeTotals('t.project_id = ? AND t.phase_id IS NULL', [$projectId]);
        if (!$groups || $t['unbilled_count'] || $t['billed_minutes'] || $t['draft_count']) {
            $g = [
                'key' => 'none', 'phase_id' => null, 'label' => __('phases.no_phase'), 'name' => __('phases.no_phase'),
                'oos' => false, 'active' => true, 'fee' => null, 'fee_billed' => 0.0, 'extra_billed' => 0.0, 'remaining' => null,
                'overdue' => false, 'completed' => false,
                'statements' => self::statementsFor('l.project_id = ? AND l.phase_id IS NULL', [$projectId]),
            ] + $t;
            $g['state'] = self::state($t);
            $groups[] = $g;
        }
        return $groups;
    }

    /** Faturalanmamış (dökümde olmayan) zaman kayıtları. */
    public static function unbilledEntries(int $projectId, ?int $phaseId, bool $unphasedOnly, ?string $from = null, ?string $to = null): array
    {
        $where = 't.project_id = ? AND t.statement_id IS NULL AND t.is_billable = 1';
        $params = [$projectId];
        if ($from) {
            $where .= ' AND t.work_date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where .= ' AND t.work_date <= ?';
            $params[] = $to;
        }
        if ($phaseId) {
            $where .= ' AND t.phase_id = ?';
            $params[] = $phaseId;
        } elseif ($unphasedOnly) {
            $where .= ' AND t.phase_id IS NULL';
        }
        return DB::all(
            "SELECT t.*, u.name AS user_name FROM time_entries t JOIN users u ON u.id = t.user_id
             WHERE $where ORDER BY t.work_date, t.id",
            $params
        );
    }

    /** Satırı koşula uyan (iptal edilmemiş) dökümler; aşamaya düşen tutar ile. */
    private static function statementsFor(string $where, array $params): array
    {
        return DB::all(
            "SELECT s.id, s.number, s.status, s.issue_date, s.due_date, s.created_at, s.total, s.currency, s.paid_amount,
                    COALESCE(SUM(CASE WHEN l.is_info = 0 THEN l.amount END), 0) AS part_amount
             FROM statement_lines l JOIN statements s ON s.id = l.statement_id
             WHERE s.status <> 'cancelled' AND $where
             GROUP BY s.id
             ORDER BY COALESCE(s.issue_date, DATE(s.created_at)) DESC, s.id DESC",
            $params
        );
    }
}
