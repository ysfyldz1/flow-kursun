<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/**
 * Hizmet dökümü yaşam döngüsü:
 *   taslak (zaman kayıtları bağlanır, satırlar düzenlenebilir)
 *   → kesinleşmiş (numara verilir, kilitlenir; fatura yüklenir, ödemeler girilir)
 *   → iptal (ödeme yoksa; bağlı zaman kayıtları serbest kalır)
 * Ödeme durumu ödenen tutardan türetilir: unpaid / partial / paid (+ vadesi geçmişse overdue).
 */
final class StatementService
{
    public const METHODS = ['transfer', 'cash', 'card', 'other'];

    public static function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = $filters['status'] ?? 'open';
        if ($status === 'draft') {
            $where[] = "s.status = 'draft'";
        } elseif ($status === 'open') {
            $where[] = "s.status = 'issued' AND s.paid_amount < s.total";
        } elseif ($status === 'paid') {
            $where[] = "s.status = 'issued' AND s.paid_amount >= s.total";
        } elseif ($status === 'cancelled') {
            $where[] = "s.status = 'cancelled'";
        }
        if (!empty($filters['client_id'])) {
            $where[] = 's.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(s.number LIKE ? OR c.name LIKE ? OR s.invoice_number LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        return DB::all(
            "SELECT s.*, c.name AS client_name, c.code AS client_code,
                    (SELECT COUNT(*) FROM payment_notices n WHERE n.statement_id = s.id AND n.status = 'pending') AS pending_notices
             FROM statements s JOIN clients c ON c.id = s.client_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(s.issue_date, s.created_at) DESC, s.id DESC",
            $params
        );
    }

    public static function find(int $id): ?array
    {
        $s = DB::one(
            'SELECT s.*, c.name AS client_name, c.code AS client_code, c.type AS client_type,
                    (SELECT name FROM project_phases WHERE id = s.phase_id) AS scope_phase_name,
                    (SELECT CONCAT(number, \' · \', name) FROM projects WHERE id = s.project_id) AS scope_project_name,
                    c.tax_id AS client_tax_id, c.tax_office AS client_tax_office,
                    c.address AS client_address, c.city AS client_city, c.country AS client_country
             FROM statements s JOIN clients c ON c.id = s.client_id WHERE s.id = ?',
            [$id]
        );
        if ($s) {
            $s['lines'] = DB::all(
                'SELECT l.*, p.number AS project_number, p.name AS project_name,
                        ph.name AS phase_name, ph.is_out_of_scope AS phase_oos, ph.position AS phase_position,
                        (SELECT 1 FROM project_phases x WHERE x.project_id = l.project_id LIMIT 1) AS project_phased
                 FROM statement_lines l LEFT JOIN projects p ON p.id = l.project_id
                 LEFT JOIN project_phases ph ON ph.id = l.phase_id
                 WHERE l.statement_id = ?
                 ORDER BY l.project_id, l.phase_id IS NULL, ph.is_out_of_scope, ph.position, l.sort < 0 DESC, l.is_info, l.sort, l.work_date, l.id',
                [$id]
            );
            $s['payments'] = DB::all(
                'SELECT p.*, u.name AS user_name FROM payments p LEFT JOIN users u ON u.id = p.created_by
                 WHERE p.statement_id = ? ORDER BY p.paid_at, p.id',
                [$id]
            );
        }
        return $s;
    }

    /** unpaid | partial | paid | overdue | draft | cancelled */
    public static function paymentStatus(array $s): string
    {
        if ($s['status'] !== 'issued') {
            return $s['status'];
        }
        if ((float) $s['paid_amount'] >= (float) $s['total'] - 0.005) {
            return 'paid';
        }
        if ($s['due_date'] && $s['due_date'] < date('Y-m-d')) {
            return 'overdue';
        }
        return (float) $s['paid_amount'] > 0 ? 'partial' : 'unpaid';
    }

    public static function balance(array $s): float
    {
        return round((float) $s['total'] - (float) $s['paid_amount'], 2);
    }

    /** Dökülmemiş faturalanabilir zaman kaydı ya da masrafı olan müvekkil / para birimi ikilileri */
    public static function unbilledSummary(): array
    {
        return DB::all(
            "SELECT c.id AS client_id, c.code, c.name, x.currency,
                    SUM(x.entries) AS entries, SUM(x.expenses) AS expenses, SUM(x.minutes) AS minutes,
                    SUM(x.amount) AS amount, MIN(x.d) AS first_date, MAX(x.d) AS last_date
             FROM (
                SELECT t.client_id, t.currency, 1 AS entries, 0 AS expenses, t.minutes, COALESCE(t.amount, 0) AS amount, t.work_date AS d
                FROM time_entries t JOIN projects p ON p.id = t.project_id
                WHERE t.statement_id IS NULL AND t.is_billable = 1 AND p.billing_type <> 'non_billable'
                UNION ALL
                SELECT e.client_id, e.currency, 0, 1, 0, e.amount, e.expense_date
                FROM expenses e WHERE e.statement_id IS NULL AND e.is_billable = 1 AND e.from_advance = 0
             ) x
             JOIN clients c ON c.id = x.client_id
             GROUP BY c.id, c.code, c.name, x.currency
             ORDER BY c.name"
        );
    }

    public static function tabCounts(): array
    {
        $r = DB::one("SELECT
                SUM(status = 'issued' AND paid_amount < total)  AS open,
                SUM(status = 'issued' AND paid_amount >= total) AS paid,
                SUM(status = 'draft')     AS draft,
                SUM(status = 'cancelled') AS cancelled,
                COUNT(*) AS `all`
            FROM statements") ?? [];
        return array_map('intval', $r);
    }

    public static function countOpen(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM statements WHERE status = 'issued' AND paid_amount < total");
    }

    // --- Taslak ---

    public static function createDraft(array $in, string $channel = 'web'): int
    {
        $clientId = (int) ($in['client_id'] ?? 0);
        $currency = (string) ($in['currency'] ?? '');
        $start = (string) ($in['period_start'] ?? '') ?: null;
        $end = (string) ($in['period_end'] ?? '') ?: date('Y-m-d');

        $client = DB::one('SELECT * FROM clients WHERE id = ?', [$clientId]);
        $errors = [];
        if (!$client) {
            $errors['client_id'] = __('validation.required');
        }
        if (!in_array($currency, UserService::CURRENCIES, true)) {
            $errors['currency'] = __('validation.required');
        }
        foreach (['period_start' => $start, 'period_end' => $end] as $k => $d) {
            if ($d !== null && !self::validDate($d)) {
                $errors[$k] = __('validation.date');
            }
        }
        // Kurşun: kapsam — tek proje ya da tek aşama
        $scopeProject = (int) ($in['project_id'] ?? 0) ?: null;
        $scopePhase = (int) ($in['phase_id'] ?? 0) ?: null;
        if ($scopeProject && $client && !DB::value('SELECT 1 FROM projects WHERE id = ? AND client_id = ? AND currency = ?', [$scopeProject, $client['id'], $currency])) {
            $errors['project_id'] = __('validation.required');
        }
        if ($scopePhase && (!$scopeProject || !DB::value('SELECT 1 FROM project_phases WHERE id = ? AND project_id = ?', [$scopePhase, $scopeProject]))) {
            $errors['phase_id'] = __('validation.required');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return DB::transaction(function () use ($client, $currency, $start, $end, $channel, $scopeProject, $scopePhase) {
            $isCompany = $client['type'] === 'company';
            $id = DB::insert('statements', [
                'client_id'        => $client['id'],
                'project_id'       => $scopeProject,
                'phase_id'         => $scopePhase,
                'currency'         => $currency,
                'locale'           => $client['locale'],
                'period_start'     => $start,
                'period_end'       => $end,
                'vat_rate'         => (float) Settings::get('billing.vat_rate', 20),
                'withholding_rate' => $isCompany ? (float) Settings::get('billing.withholding_rate', 20) : 0,
                'show_bank'        => Settings::get('billing.bank_accounts') ? 1 : 0,
                'created_by'       => Auth::id(),
            ]);

            $projects = DB::all(
                "SELECT * FROM projects WHERE client_id = ? AND currency = ? AND billing_type <> 'non_billable'"
                . ($scopeProject ? ' AND id = ' . (int) $scopeProject : '') . ' ORDER BY number',
                [$client['id'], $currency]
            );
            $sort = 0;
            foreach ($projects as $p) {
                $params = $start ? [$p['id'], $end, $start] : [$p['id'], $end];
                if ($scopePhase) {
                    $params[] = $scopePhase;
                }
                $entries = DB::all(
                    'SELECT t.*, u.name AS user_name FROM time_entries t JOIN users u ON u.id = t.user_id
                     WHERE t.project_id = ? AND t.statement_id IS NULL AND t.is_billable = 1
                       AND t.work_date <= ? ' . ($start ? 'AND t.work_date >= ?' : '') . '
                       ' . ($scopePhase ? 'AND t.phase_id = ?' : '') . '
                     ORDER BY t.work_date, t.id',
                    $params
                );

                // Kurşun: aşamalı saatlik proje ayrı yoldan dökülür
                if ($p['billing_type'] === 'hourly' && PhaseService::hasPhases((int) $p['id'])) {
                    foreach ($entries as $t) {
                        self::insertTimeLine($id, $t, false, $sort);
                    }
                    // Kayıt giren aşamalara (aşama dökümünde o aşamaya her durumda) kalan bedel satırı
                    $phaseIds = array_unique(array_filter(array_map(fn ($t) => (int) $t['phase_id'], $entries)));
                    if ($scopePhase) {
                        $phaseIds = [$scopePhase];
                    }
                    foreach ($phaseIds as $phaseId) {
                        self::addPhaseFee($id, (int) $phaseId);
                    }
                    continue;
                }

                $flat = null;
                if ($p['billing_type'] === 'retainer' && $p['retainer_amount'] !== null) {
                    $flat = ['type' => 'retainer', 'amount' => (float) $p['retainer_amount']];
                } elseif ($p['billing_type'] === 'fixed' && $p['fixed_fee'] !== null && !self::fixedFeeBilled((int) $p['id'])) {
                    $flat = ['type' => 'fixed', 'amount' => (float) $p['fixed_fee']];
                }
                if (!$entries && !$flat) {
                    continue;
                }

                if ($flat) {
                    DB::insert('statement_lines', [
                        'statement_id' => $id,
                        'type'         => $flat['type'],
                        'project_id'   => $p['id'],
                        'description'  => $p['name'],
                        'amount'       => $flat['amount'],
                        'sort'         => $sort++,
                    ]);
                }
                $isInfo = $p['billing_type'] !== 'hourly';
                foreach ($entries as $t) {
                    self::insertTimeLine($id, $t, $isInfo, $sort);
                }
            }
            self::syncPhaseLines($id);

            // Yansıtılacak masraflar (faturalanmaz projelerin masrafları da dahil)
            $expenses = DB::all(
                'SELECT e.*, u.name AS user_name FROM expenses e JOIN users u ON u.id = e.user_id
                 WHERE e.client_id = ? AND e.currency = ? AND e.statement_id IS NULL AND e.is_billable = 1 AND e.from_advance = 0
                   AND e.expense_date <= ? ' . ($start ? 'AND e.expense_date >= ?' : '') . '
                   ' . ($scopePhase ? 'AND 1 = 0' : ($scopeProject ? 'AND e.project_id = ' . (int) $scopeProject : '')) . '
                 ORDER BY e.project_id, e.expense_date, e.id',
                $start ? [$client['id'], $currency, $end, $start] : [$client['id'], $currency, $end]
            );
            $previous = \App\Core\Lang::locale();
            \App\Core\Lang::set($client['locale']);   // kategori adı müvekkilin dilinde yazılsın
            foreach ($expenses as $ex) {
                DB::insert('statement_lines', [
                    'statement_id' => $id,
                    'type'         => 'expense',
                    'project_id'   => $ex['project_id'],
                    'expense_id'   => $ex['id'],
                    'work_date'    => $ex['expense_date'],
                    'user_name'    => $ex['user_name'],
                    'description'  => __('expenses.cat_' . $ex['category']) . ' – ' . $ex['description'],
                    'amount'       => $ex['amount'],
                    'sort'         => 500 + $sort++,
                ]);
                DB::update('expenses', ['statement_id' => $id], 'id = ?', [$ex['id']]);
            }
            \App\Core\Lang::set($previous);

            if (!DB::value('SELECT COUNT(*) FROM statement_lines WHERE statement_id = ?', [$id])) {
                throw new ValidationException(['client_id' => __('billing.nothing_to_bill')]);
            }
            self::applyDefaultDiscount($id, $client);
            self::recalculate($id);
            Audit::log('statement.drafted', 'statement', $id, ['client_id' => $client['id'], 'currency' => $currency], $channel);
            return $id;
        });
    }

    /** Zaman kaydını döküme satır olarak ekler ve kaydı dökümle kilitler. */
    private static function insertTimeLine(int $id, array $t, bool $isInfo, int &$sort): int
    {
        // Kayıt girildiğinde ücret tanımlı değildiyse şimdi bulunur (kayda da yazılır)
        if ($t['rate'] === null) {
            $r = RateService::resolve((int) $t['project_id'], (int) $t['user_id']);
            if ($r['rate'] !== null) {
                $t['rate'] = $r['rate'];
                $t['amount'] = round($r['rate'] * $t['minutes'] / 60, 2);
                DB::update('time_entries', ['rate' => $t['rate'], 'amount' => $t['amount'], 'rate_source' => $r['source']], 'id = ?', [$t['id']]);
            }
        }
        $phased = $t['phase_id'] !== null;
        $lineId = DB::insert('statement_lines', [
            'statement_id'  => $id,
            'type'          => 'time',
            'project_id'    => $t['project_id'],
            'phase_id'      => $t['phase_id'],
            'time_entry_id' => $t['id'],
            'work_date'     => $t['work_date'],
            'user_name'     => $t['user_name'] ?? DB::value('SELECT name FROM users WHERE id = ?', [$t['user_id']]),
            'description'   => $t['description'],
            'hours'         => round($t['minutes'] / 60, 2),
            'rate'          => $isInfo && !$phased ? null : $t['rate'],
            'amount'        => $isInfo ? null : $t['amount'],
            'is_info'       => (int) $isInfo,
            'sort'          => $sort++,
        ]);
        DB::update('time_entries', ['statement_id' => $id], 'id = ?', [$t['id']]);
        return $lineId;
    }

    // --- Kurşun: aşamalı döküm ---

    /**
     * Aşamanın faturalanma durumu (iptal edilmemiş dökümlerde; $exclude dökümü hariç):
     * billed = bedele sayılan tutar (bedel satırları + zamana göre faturalanan zaman), extra = ek zaman bedelleri,
     * statements = aşamaya satır giren döküm sayısı.
     */
    public static function phaseBilled(int $phaseId, ?int $exclude = null): array
    {
        $r = DB::one(
            "SELECT COALESCE(SUM(CASE WHEN l.type IN ('phase_fee', 'time') AND l.is_info = 0 THEN l.amount END), 0) AS billed,
                    COALESCE(SUM(CASE WHEN l.type = 'phase_extra' THEN l.amount END), 0) AS extra,
                    COALESCE(SUM(CASE WHEN l.type = 'time' THEN l.hours * COALESCE(l.rate, 0) END), 0) AS time_value,
                    COUNT(DISTINCT CASE WHEN l.is_info = 0 THEN s.id END) AS statements
             FROM statement_lines l JOIN statements s ON s.id = l.statement_id
             WHERE l.phase_id = ? AND s.status = 'issued' AND s.id <> ?",
            [$phaseId, (int) $exclude]
        );
        return ['billed' => (float) $r['billed'], 'extra' => (float) $r['extra'], 'time_value' => (float) $r['time_value'], 'statements' => (int) $r['statements']];
    }

    /** Aşama bedeli satırı ekler (kalan bedel kadar). Bedelsiz/kapsam dışı/onaysız aşamada ya da zaten varsa bir şey yapmaz. */
    public static function addPhaseFee(int $id, int $phaseId): bool
    {
        $ph = DB::one('SELECT * FROM project_phases WHERE id = ?', [$phaseId]);
        if (!$ph || $ph['is_out_of_scope'] || $ph['fee'] === null || ($ph['is_optional'] && !$ph['approved_at'])) {
            return false;
        }
        if (DB::value("SELECT 1 FROM statement_lines WHERE statement_id = ? AND phase_id = ? AND type = 'phase_fee'", [$id, $phaseId])) {
            return false;
        }
        $remaining = round((float) $ph['fee'] - self::phaseBilled($phaseId, $id)['billed'], 2);
        if ($remaining <= 0) {
            return false;
        }
        $locale = (string) DB::value('SELECT locale FROM statements WHERE id = ?', [$id]);
        $previous = \App\Core\Lang::locale();
        \App\Core\Lang::set($locale);
        $desc = __('billing.phase_fee_desc', ['name' => $ph['name']]);
        \App\Core\Lang::set($previous);
        DB::insert('statement_lines', [
            'statement_id' => $id,
            'type'         => 'phase_fee',
            'project_id'   => $ph['project_id'],
            'phase_id'     => $phaseId,
            'description'  => $desc,
            'amount'       => $remaining,
            'sort'         => -2,
        ]);
        return true;
    }

    /** Ek zaman bedeli: bu dökümdeki zaman değeri − bedel satırı (pozitifse). */
    public static function addPhaseExtra(int $id, int $phaseId, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $g = self::phaseGroups($id)[$phaseId] ?? throw new ValidationException(['statement' => __('validation.required')]);
        $locale = (string) DB::value('SELECT locale FROM statements WHERE id = ?', [$id]);
        $previous = \App\Core\Lang::locale();
        \App\Core\Lang::set($locale);
        $desc = __('billing.phase_extra_desc', ['name' => $g['name']]);
        \App\Core\Lang::set($previous);
        DB::insert('statement_lines', [
            'statement_id' => $id,
            'type'         => 'phase_extra',
            'project_id'   => $g['project_id'],
            'phase_id'     => $phaseId,
            'description'  => $desc,
            'amount'       => max(0, $g['over']),
            'sort'         => -1,
        ]);
        self::recalculate($id);
        Audit::log('statement.phase_extra', 'statement', $id, ['phase_id' => $phaseId], $channel);
    }

    public static function addPhaseFeeLine(int $id, int $phaseId, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        if (!self::addPhaseFee($id, $phaseId)) {
            throw new ValidationException(['statement' => __('billing.phase_fee_none_left')]);
        }
        self::syncPhaseLines($id);
        self::recalculate($id);
        Audit::log('statement.phase_fee', 'statement', $id, ['phase_id' => $phaseId], $channel);
    }

    /**
     * Aşamalı zaman satırlarını gruba göre ayarlar: grupta bedel satırı varsa bilgi amaçlı (dahil),
     * yoksa saat × ücret ile faturalanır. Kapsam dışı ve aşamasız satırlar her zaman faturalanır.
     */
    public static function syncPhaseLines(int $id): void
    {
        $withFee = array_map('intval', array_column(DB::all("SELECT DISTINCT phase_id FROM statement_lines WHERE statement_id = ? AND type = 'phase_fee'", [$id]), 'phase_id'));
        $lines = DB::all("SELECT id, phase_id, hours, rate, amount, is_info FROM statement_lines WHERE statement_id = ? AND type = 'time' AND phase_id IS NOT NULL", [$id]);
        foreach ($lines as $l) {
            $info = in_array((int) $l['phase_id'], $withFee, true);
            if ($info === (bool) $l['is_info']) {
                continue;
            }
            DB::update('statement_lines', [
                'is_info' => (int) $info,
                'amount'  => $info ? null : ($l['rate'] !== null ? round((float) $l['hours'] * (float) $l['rate'], 2) : null),
            ], 'id = ?', [$l['id']]);
        }
        // Bedel satırı kalmayan aşamanın ek zaman bedeli de anlamını yitirir
        DB::query("DELETE x FROM statement_lines x WHERE x.statement_id = ? AND x.type = 'phase_extra'
                   AND NOT EXISTS (SELECT 1 FROM (SELECT phase_id FROM statement_lines WHERE statement_id = ? AND type = 'phase_fee') f WHERE f.phase_id = x.phase_id)", [$id, $id]);
    }

    /**
     * Taslak ekranı için aşama grubu bilgisi: phase_id => bedel, önceki dökümler, bu dökümdeki zaman değeri, aşım.
     */
    public static function phaseGroups(int $id): array
    {
        $rows = DB::all(
            "SELECT ph.id, ph.name, ph.project_id, ph.fee, ph.is_out_of_scope, ph.is_optional, ph.approved_at,
                    SUM(CASE WHEN l.type = 'time' THEN l.hours * COALESCE(l.rate, 0) ELSE 0 END) AS time_value,
                    SUM(CASE WHEN l.type = 'time' THEN l.hours ELSE 0 END) AS hours,
                    SUM(CASE WHEN l.type = 'phase_fee' THEN l.amount ELSE 0 END) AS fee_line,
                    SUM(l.type = 'phase_fee') AS has_fee,
                    SUM(l.type = 'phase_extra') AS has_extra
             FROM statement_lines l JOIN project_phases ph ON ph.id = l.phase_id
             WHERE l.statement_id = ?
             GROUP BY ph.id",
            [$id]
        );
        $out = [];
        foreach ($rows as $r) {
            $prev = self::phaseBilled((int) $r['id'], $id);
            $fee = $r['fee'] !== null ? (float) $r['fee'] : null;
            $out[(int) $r['id']] = [
                'id'           => (int) $r['id'],
                'name'         => $r['is_out_of_scope'] ? __('phases.out_of_scope') : $r['name'],
                'project_id'   => (int) $r['project_id'],
                'oos'          => (bool) $r['is_out_of_scope'],
                'fee'          => $fee,
                'billed_before'=> $prev['billed'],
                'statements_before' => $prev['statements'],
                'remaining'    => $fee !== null ? round($fee - $prev['billed'], 2) : null,
                'time_value'   => round((float) $r['time_value'], 2),
                'hours'        => (float) $r['hours'],
                'fee_line'     => (float) $r['fee_line'],
                'has_fee'      => (bool) $r['has_fee'],
                'has_extra'    => (bool) $r['has_extra'],
                // Bedel satırı varken aşamanın toplam zaman değeri (önceki dökümler + bu döküm) bedeli
                // geçtiyse, daha önce ek bedel olarak kesilmemiş fark
                'over'         => $r['has_fee'] && $fee !== null
                    ? max(0.0, round($prev['time_value'] + (float) $r['time_value'] - $fee - $prev['extra'], 2))
                    : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Satırları proje ve (aşamalı projede) aşamaya göre gruplar. Ekran ve PDF aynısını kullanır.
     * @return list<array{title: string, phase: ?string, phase_id: ?int, project_id: ?int, lines: list<array>, total: float}>
     */
    public static function groupLines(array $lines): array
    {
        $groups = [];
        foreach ($lines as $l) {
            $phased = $l['project_id'] && $l['project_phased'];
            $key = ($l['project_id'] ?: 0) . ':' . ($phased ? ($l['phase_id'] ?: 'none') : '');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'title'      => $l['project_id'] ? $l['project_number'] . ' · ' . $l['project_name'] : __('billing.other_items'),
                    'phase'      => !$phased ? null : ($l['phase_id'] ? ($l['phase_oos'] ? __('phases.out_of_scope') : $l['phase_name']) : null),
                    'phase_id'   => $phased && $l['phase_id'] ? (int) $l['phase_id'] : null,
                    'project_id' => $l['project_id'] ? (int) $l['project_id'] : null,
                    'lines'      => [],
                    'total'      => 0.0,
                ];
            }
            // Aşamalı projede aşamasız zaman kaydı varsa grup "Aşamasız" diye adlandırılır
            if ($phased && !$l['phase_id'] && $l['type'] === 'time') {
                $groups[$key]['phase'] = __('phases.no_phase');
            }
            $groups[$key]['lines'][] = $l;
            $groups[$key]['total'] += $l['is_info'] ? 0 : (float) $l['amount'];
        }
        return array_values($groups);
    }

    /** Projede seçilebilecek aşamalar (taşıma/bölme için). */
    public static function projectPhases(int $id): array
    {
        $out = [];
        $projects = array_column(DB::all('SELECT DISTINCT project_id FROM statement_lines WHERE statement_id = ? AND project_id IS NOT NULL', [$id]), 'project_id');
        foreach ($projects as $pid) {
            $no = 0;
            foreach (PhaseService::forProject((int) $pid) as $ph) {
                $out[(int) $pid][] = [
                    'id'     => (int) $ph['id'],
                    'name'   => ($ph['is_out_of_scope'] ? '' : ++$no . '. ') . $ph['name'],
                    'active' => $ph['is_active'],
                ];
            }
        }
        return array_filter($out);
    }

    /**
     * Satırın aşamasını değiştirir (zaman kaydı da taşınır). Aşama dökümündeyken başka aşamaya
     * taşınan kayıt dökümden çıkar ve serbest kalır.
     */
    private static function moveLine(int $id, array $line, ?int $phaseId): void
    {
        if ($phaseId !== null) {
            $ok = DB::one('SELECT is_optional, approved_at FROM project_phases WHERE id = ? AND project_id = ?', [$phaseId, $line['project_id']]);
            if (!$ok || ($ok['is_optional'] && !$ok['approved_at'])) {
                return;
            }
        }
        DB::update('time_entries', ['phase_id' => $phaseId], 'id = ?', [$line['time_entry_id']]);
        $scope = DB::value('SELECT phase_id FROM statements WHERE id = ?', [$id]);
        if ($scope !== null && (int) $scope !== $phaseId) {
            DB::update('time_entries', ['statement_id' => null], 'id = ? AND statement_id = ?', [$line['time_entry_id'], $id]);
            DB::query('DELETE FROM statement_lines WHERE id = ?', [$line['id']]);
            return;
        }
        DB::update('statement_lines', ['phase_id' => $phaseId], 'id = ?', [$line['id']]);
    }

    /**
     * Zaman satırını parçalara böler: her parça (süre, aşama, açıklama) ayrı zaman kaydı olur.
     * Parçaların toplamı satırdaki süreye eşit olmalı. İlk parça mevcut kaydı günceller.
     */
    public static function splitLine(int $id, int $lineId, array $parts, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $line = DB::one("SELECT * FROM statement_lines WHERE id = ? AND statement_id = ? AND type = 'time'", [$lineId, $id]) ?? throw new ValidationException(['split' => __('validation.required')]);
        $entry = DB::one('SELECT * FROM time_entries WHERE id = ?', [$line['time_entry_id']]) ?? throw new ValidationException(['split' => __('validation.required')]);

        $clean = [];
        $total = 0;
        foreach ($parts as $p) {
            $raw = trim((string) ($p['hours'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $minutes = TimeService::parseDuration($raw);
            $desc = trim((string) ($p['description'] ?? ''));
            if ($minutes === null || $minutes <= 0 || $desc === '') {
                throw new ValidationException(['split' => __('billing.split_invalid')]);
            }
            $phaseId = (int) ($p['phase_id'] ?? 0) ?: null;
            if ($phaseId !== null && !DB::value('SELECT 1 FROM project_phases WHERE id = ? AND project_id = ? AND (is_optional = 0 OR approved_at IS NOT NULL)', [$phaseId, $entry['project_id']])) {
                throw new ValidationException(['split' => __('phases.invalid_choice')]);
            }
            $clean[] = ['minutes' => $minutes, 'description' => $desc, 'phase_id' => $phaseId];
            $total += $minutes;
        }
        $lineMinutes = (int) round((float) $line['hours'] * 60);
        if (count($clean) < 2) {
            throw new ValidationException(['split' => __('billing.split_two')]);
        }
        if (abs($total - $lineMinutes) > 1) {
            throw new ValidationException(['split' => __('billing.split_sum', ['total' => TimeService::formatMinutes($lineMinutes)])]);
        }

        DB::transaction(function () use ($id, $line, $entry, $clean, $channel) {
            $rate = $entry['rate'] !== null ? (float) $entry['rate'] : null;
            $amountFor = fn (int $m) => ($entry['is_billable'] && $entry['amount'] !== null && $rate !== null) ? round($rate * $m / 60, 2) : null;
            $scopePhase = DB::value('SELECT phase_id FROM statements WHERE id = ?', [$id]);
            DB::query('DELETE FROM statement_lines WHERE id = ?', [$line['id']]);
            $sort = (int) $line['sort'];
            foreach ($clean as $i => $part) {
                $data = [
                    'minutes'     => $part['minutes'],
                    'raw_minutes' => $part['minutes'],
                    'description' => $part['description'],
                    'phase_id'    => $part['phase_id'],
                    'amount'      => $amountFor($part['minutes']),
                ];
                if ($i === 0) {
                    DB::update('time_entries', $data, 'id = ?', [$entry['id']]);
                    $tid = (int) $entry['id'];
                } else {
                    $copy = array_diff_key($entry, array_flip(['id', 'created_at', 'updated_at', 'statement_id']));
                    $tid = DB::insert('time_entries', array_merge($copy, $data, ['created_by' => Auth::id()]));
                }
                $t = DB::one('SELECT * FROM time_entries WHERE id = ?', [$tid]);
                // Aşama dökümünde başka aşamaya ayrılan parça dökümden çıkar, serbest kalır
                if ($scopePhase !== null && (int) $scopePhase !== $part['phase_id']) {
                    DB::update('time_entries', ['statement_id' => null], 'id = ?', [$tid]);
                    continue;
                }
                $t['user_name'] = $line['user_name'];
                self::insertTimeLine($id, $t, false, $sort);
            }
            self::syncPhaseLines($id);
            self::recalculate($id);
            Audit::log('statement.line_split', 'statement', $id, ['time_entry_id' => $entry['id'], 'parts' => count($clean)], $channel);
        });
    }

    /**
     * Kurşun: varsayılan iskonto. Döküm tek projeden oluşuyorsa ve projenin iskontosu varsa o,
     * yoksa müvekkilin iskontosu (toplanmaz). Taslakta elle değiştirilebilir.
     */
    public static function applyDefaultDiscount(int $id, array $client): void
    {
        $projectIds = array_column(DB::all('SELECT DISTINCT project_id FROM statement_lines WHERE statement_id = ? AND project_id IS NOT NULL', [$id]), 'project_id');
        $pct = null;
        if (count($projectIds) === 1) {
            $pct = DB::value('SELECT discount_pct FROM projects WHERE id = ?', [$projectIds[0]]);
        }
        $pct ??= $client['discount_pct'] ?? null;
        if ($pct !== null && (float) $pct > 0) {
            DB::update('statements', ['discount_type' => 'percent', 'discount_value' => (float) $pct], 'id = ?', [$id]);
        }
    }

    public static function updateDraft(int $id, array $in, string $channel = 'web'): void
    {
        $s = self::draftOrFail($id);
        $errors = [];

        $vat = parse_money($in['vat_rate'] ?? '0');
        $wh = parse_money($in['withholding_rate'] ?? '0');
        if ($vat === false || $vat > 100) {
            $errors['vat_rate'] = __('validation.number');
        }
        if ($wh === false || $wh > 100) {
            $errors['withholding_rate'] = __('validation.number');
        }
        $discountType = ($in['discount_type'] ?? '') === 'amount' ? 'amount' : 'percent';
        $discount = parse_money($in['discount_value'] ?? '');
        if ($discount === false || ($discountType === 'percent' && $discount > 100)) {
            $errors['discount_value'] = __('validation.number');
        }
        $due = (string) ($in['due_date'] ?? '') ?: null;
        if ($due !== null && !self::validDate($due)) {
            $errors['due_date'] = __('validation.date');
        }

        // Satır düzenlemeleri: açıklama, saat, ücret, tutar
        $lines = [];
        foreach ((array) ($in['lines'] ?? []) as $lineId => $l) {
            $lineId = (int) $lineId;
            $amount = parse_money($l['amount'] ?? '');
            if ($amount === false) {
                $errors["lines.$lineId.amount"] = __('validation.number');
            }
            $desc = trim((string) ($l['description'] ?? ''));
            if ($desc === '') {
                $errors["lines.$lineId.description"] = __('validation.required');
            }
            $row = ['description' => $desc, 'amount' => $amount === false ? null : $amount];
            if (array_key_exists('hours', $l)) {
                $minutes = TimeService::parseDuration((string) $l['hours']);
                if ($minutes === null || $minutes <= 0) {
                    $errors["lines.$lineId.hours"] = __('validation.number');
                } else {
                    $row['hours'] = round($minutes / 60, 2);
                }
            }
            if (array_key_exists('rate', $l)) {
                $rate = parse_money($l['rate']);
                if ($rate === false) {
                    $errors["lines.$lineId.rate"] = __('validation.number');
                } else {
                    $row['rate'] = $rate;
                }
            }
            if (array_key_exists('phase_id', $l)) {
                $row['phase_id'] = (int) $l['phase_id'] ?: null;
            }
            $lines[$lineId] = $row;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        DB::transaction(function () use ($id, $s, $in, $vat, $wh, $due, $lines, $discountType, $discount, $channel) {
            foreach ($lines as $lineId => $l) {
                $line = DB::one('SELECT * FROM statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $id]);
                if (!$line) {
                    continue;
                }
                $data = ['description' => $l['description']];
                if ($line['type'] === 'time') {
                    // Saat dökümde düzeltilir; zaman kaydının kendisi (çalışılan süre) değişmez
                    $data['hours'] = $l['hours'] ?? $line['hours'];
                }
                if (!$line['is_info']) {
                    $data['amount'] = $l['amount'];
                    if ($line['type'] === 'time' && array_key_exists('rate', $l)) {
                        $data['rate'] = $l['rate'];
                        // Saat ya da ücret değiştiyse tutar yeniden hesaplanır; yoksa elle girilen tutar kalır
                        $changed = abs((float) $data['hours'] - (float) $line['hours']) > 0.001
                            || $l['rate'] !== ($line['rate'] === null ? null : (float) $line['rate']);
                        if ($changed && $l['rate'] !== null) {
                            $data['amount'] = round((float) $data['hours'] * $l['rate'], 2);
                        }
                    }
                }
                DB::update('statement_lines', $data, 'id = ?', [$lineId]);
                // Kurşun: aşama değiştiyse kayıt o aşamaya taşınır
                if ($line['type'] === 'time' && array_key_exists('phase_id', $l) && $l['phase_id'] !== ($line['phase_id'] === null ? null : (int) $line['phase_id'])) {
                    self::moveLine($id, $line, $l['phase_id']);
                }
            }
            self::syncPhaseLines($id);
            DB::update('statements', [
                'vat_rate'         => $vat ?? 0,
                'withholding_rate' => $wh ?? 0,
                'discount_type'    => $discount ? $discountType : null,
                'discount_value'   => $discount ?: null,
                'due_date'         => $due,
                'notes'            => trim((string) ($in['notes'] ?? '')) ?: null,
                'show_bank'        => !empty($in['show_bank']) ? 1 : 0,
            ], 'id = ?', [$id]);
            self::recalculate($id);
            Audit::log('statement.updated', 'statement', $id, [], $channel);
        });
    }

    /** Masraf ya da diğer kalem ekler. */
    public static function addLine(int $id, array $in, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $type = in_array($in['type'] ?? '', ['expense', 'other'], true) ? $in['type'] : 'other';
        $desc = trim((string) ($in['description'] ?? ''));
        $amount = parse_money($in['amount'] ?? '');
        $errors = [];
        if ($desc === '') {
            $errors['new_description'] = __('validation.required');
        }
        if ($amount === null || $amount === false) {
            $errors['new_amount'] = __('validation.number');
        }
        $projectId = (int) ($in['project_id'] ?? 0) ?: null;
        if ($errors) {
            throw new ValidationException($errors);
        }
        DB::insert('statement_lines', [
            'statement_id' => $id,
            'type'         => $type,
            'project_id'   => $projectId,
            'description'  => $desc,
            'amount'       => $amount,
            'sort'         => 1000,
        ]);
        self::recalculate($id);
        Audit::log('statement.line_added', 'statement', $id, ['type' => $type, 'amount' => $amount], $channel);
    }

    /** Satırı çıkarır; zaman kaydıysa kayıt serbest kalır (sonraki döküme girebilir). */
    public static function removeLine(int $id, int $lineId, string $channel = 'web'): void
    {
        self::draftOrFail($id);
        $line = DB::one('SELECT * FROM statement_lines WHERE id = ? AND statement_id = ?', [$lineId, $id]);
        if (!$line) {
            return;
        }
        DB::transaction(function () use ($id, $line, $channel) {
            if ($line['time_entry_id']) {
                DB::update('time_entries', ['statement_id' => null], 'id = ? AND statement_id = ?', [$line['time_entry_id'], $id]);
            }
            if ($line['expense_id']) {
                DB::update('expenses', ['statement_id' => null], 'id = ? AND statement_id = ?', [$line['expense_id'], $id]);
            }
            DB::query('DELETE FROM statement_lines WHERE id = ?', [$line['id']]);
            self::syncPhaseLines($id);
            self::recalculate($id);
            Audit::log('statement.line_removed', 'statement', $id, ['line' => $line['description']], $channel);
        });
    }

    public static function issue(int $id, string $channel = 'web'): void
    {
        $s = self::draftOrFail($id);
        $missing = DB::value('SELECT COUNT(*) FROM statement_lines WHERE statement_id = ? AND is_info = 0 AND amount IS NULL', [$id]);
        if ($missing) {
            throw new ValidationException(['issue' => __('billing.missing_amounts', ['count' => $missing])]);
        }
        if ((float) $s['total'] <= 0) {
            throw new ValidationException(['issue' => __('billing.zero_total')]);
        }
        DB::transaction(function () use ($id, $s, $channel) {
            $year = date('Y');
            $number = sprintf('HD-%s-%04d', $year, Counter::next("statement.$year"));
            DB::update('statements', [
                'number'     => $number,
                'status'     => 'issued',
                'issue_date' => date('Y-m-d'),
                'due_date'   => $s['due_date'] ?: date('Y-m-d', strtotime('+' . (int) Settings::get('billing.due_days', 15) . ' days')),
                'issued_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            Audit::log('statement.issued', 'statement', $id, ['number' => $number, 'total' => $s['total']], $channel);
        });
    }

    /** Taslak silinir; kesinleşmiş döküm ödeme yoksa iptal edilir (numara boşa düşmez, kayıt kalır). */
    public static function cancel(int $id, string $channel = 'web'): void
    {
        $s = self::find($id) ?? throw new ValidationException(['statement' => __('error.404')]);
        if ($s['status'] === 'cancelled') {
            return;
        }
        if ((float) $s['paid_amount'] > 0) {
            throw new ValidationException(['statement' => __('billing.has_payments')]);
        }
        DB::transaction(function () use ($id, $s, $channel) {
            DB::update('time_entries', ['statement_id' => null], 'statement_id = ?', [$id]);
            DB::update('expenses', ['statement_id' => null], 'statement_id = ?', [$id]);
            if ($s['status'] === 'draft') {
                DB::query('DELETE FROM statements WHERE id = ?', [$id]);
                Audit::log('statement.draft_deleted', 'statement', $id, [], $channel);
            } else {
                DB::update('statements', ['status' => 'cancelled'], 'id = ?', [$id]);
                Audit::log('statement.cancelled', 'statement', $id, ['number' => $s['number']], $channel);
            }
        });
    }

    // --- Resmi fatura ---

    public static function attachInvoice(int $id, array $file, string $invoiceNumber, string $channel = 'web'): void
    {
        $s = self::find($id);
        if (!$s || $s['status'] !== 'issued') {
            throw new ValidationException(['invoice' => __('billing.not_issued')]);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['invoice' => __('billing.invoice_required')]);
        }
        $mime = mime_content_type($file['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/xml' => 'xml', 'text/xml' => 'xml'][$mime] ?? null;
        if (!$ext) {
            throw new ValidationException(['invoice' => __('billing.invoice_type')]);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['invoice' => __('billing.invoice_size')]);
        }

        $dir = BASE_PATH . '/storage/uploads/invoices';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = sprintf('%s-%s.%s', $s['number'], bin2hex(random_bytes(4)), $ext);
        move_uploaded_file($file['tmp_name'], "$dir/$name");
        if ($s['invoice_file'] && is_file("$dir/{$s['invoice_file']}")) {
            unlink("$dir/{$s['invoice_file']}");
        }
        DB::update('statements', [
            'invoice_file'        => $name,
            'invoice_number'      => trim($invoiceNumber) ?: null,
            'invoice_uploaded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        Audit::log('statement.invoice_uploaded', 'statement', $id, ['invoice_number' => $invoiceNumber], $channel);
    }

    public static function invoicePath(array $s): ?string
    {
        $path = BASE_PATH . '/storage/uploads/invoices/' . basename((string) $s['invoice_file']);
        return $s['invoice_file'] && is_file($path) ? $path : null;
    }

    // --- Ödemeler ---

    public static function addPayment(int $id, array $in, string $source = 'office', string $channel = 'web'): void
    {
        $s = self::find($id);
        if (!$s || $s['status'] !== 'issued') {
            throw new ValidationException(['payment' => __('billing.not_issued')]);
        }
        $amount = parse_money($in['amount'] ?? '');
        $date = (string) ($in['paid_at'] ?? '') ?: date('Y-m-d');
        $errors = [];
        if (!$amount) {
            $errors['pay_amount'] = __('validation.number');
        } elseif ($amount > self::balance($s) + 0.005) {
            $errors['pay_amount'] = __('billing.overpayment', ['balance' => format_money(self::balance($s), $s['currency'])]);
        }
        if (!self::validDate($date)) {
            $errors['paid_at'] = __('validation.date');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        DB::transaction(function () use ($id, $in, $amount, $date, $source, $channel) {
            $pid = DB::insert('payments', [
                'statement_id' => $id,
                'amount'       => $amount,
                'paid_at'      => $date,
                'method'       => in_array($in['method'] ?? '', self::METHODS, true) ? $in['method'] : 'transfer',
                'reference'    => trim((string) ($in['reference'] ?? '')) ?: null,
                'notes'        => trim((string) ($in['notes'] ?? '')) ?: null,
                'source'       => $source,
                'created_by'   => Auth::id(),
            ]);
            self::recalculatePaid($id);
            Audit::log('payment.recorded', 'statement', $id, ['payment_id' => $pid, 'amount' => $amount], $channel);
        });
    }

    public static function deletePayment(int $id, int $paymentId, string $channel = 'web'): void
    {
        $p = DB::one('SELECT * FROM payments WHERE id = ? AND statement_id = ?', [$paymentId, $id]);
        if (!$p) {
            return;
        }
        DB::transaction(function () use ($id, $p, $channel) {
            DB::query('DELETE FROM payments WHERE id = ?', [$p['id']]);
            self::recalculatePaid($id);
            Audit::log('payment.deleted', 'statement', $id, $p, $channel);
        });
    }

    // --- Yardımcılar ---

    private static function recalculate(int $id): void
    {
        $s = DB::one('SELECT vat_rate, withholding_rate, discount_type, discount_value FROM statements WHERE id = ?', [$id]);
        $subtotal = round((float) DB::value('SELECT COALESCE(SUM(amount), 0) FROM statement_lines WHERE statement_id = ? AND is_info = 0', [$id]), 2);
        // Tutar olarak girilen iskonto, satırlar azalsa da ara toplamı geçmez
        $discount = $s['discount_type'] === 'amount'
            ? min((float) $s['discount_value'], $subtotal)
            : round($subtotal * (float) $s['discount_value'] / 100, 2);
        $net = round($subtotal - $discount, 2);
        $vat = round($net * (float) $s['vat_rate'] / 100, 2);
        $wh = round($net * (float) $s['withholding_rate'] / 100, 2);
        DB::update('statements', [
            'subtotal'           => $subtotal,
            'discount_amount'    => $discount,
            'vat_amount'         => $vat,
            'withholding_amount' => $wh,
            'total'              => round($net + $vat - $wh, 2),
        ], 'id = ?', [$id]);
    }

    private static function recalculatePaid(int $id): void
    {
        DB::update('statements', [
            'paid_amount' => (float) DB::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE statement_id = ?', [$id]),
        ], 'id = ?', [$id]);
    }

    private static function fixedFeeBilled(int $projectId): bool
    {
        return (bool) DB::value(
            "SELECT COUNT(*) FROM statement_lines l JOIN statements s ON s.id = l.statement_id
             WHERE l.project_id = ? AND l.type = 'fixed' AND s.status <> 'cancelled'",
            [$projectId]
        );
    }

    private static function draftOrFail(int $id): array
    {
        $s = DB::one('SELECT * FROM statements WHERE id = ?', [$id]);
        if (!$s || $s['status'] !== 'draft') {
            throw new ValidationException(['statement' => __('billing.not_draft')]);
        }
        return $s;
    }

    private static function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    }
}
