<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

final class TimeService
{
    /**
     * Süre girişini dakikaya çevirir:
     *   "1:30" → 90   "1,5" / "1.5" → 90   "2" → 120 (24'e kadar tam sayı saat)
     *   "90" → 90 (24'ten büyük tam sayı dakika)   "1s 30d" / "1h 30m" / "45dk" → dakika
     * Geçersizse null.
     */
    public static function parseDuration(string $raw): ?int
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $s, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }
        if (preg_match('/^\d*[.,]\d+$/', $s)) {
            return (int) round((float) str_replace(',', '.', $s) * 60);
        }
        if (preg_match('/^\d+$/', $s)) {
            return (int) $s <= 24 ? (int) $s * 60 : (int) $s;
        }
        if (preg_match('/^(?:(\d+)\s*(?:s|sa|saat|h)\s*)?(?:(\d+)\s*(?:d|dk|dakika|m|min))?$/u', $s, $m) && ($m[1] ?? '') . ($m[2] ?? '') !== '') {
            return (int) ($m[1] ?? 0) * 60 + (int) ($m[2] ?? 0);
        }
        return null;
    }

    /** Kurum ayarına göre: ondalık saat (1,5 — 0,1 = 6 dk) ya da saat:dakika (1:30) */
    public static function formatMinutes(int $minutes): string
    {
        if (!self::decimal()) {
            return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        // 6 dk'nın katları tek basamakla tam çıkar; yuvarlanmamış süre (düzenleme formu) iki basamak
        $h = rtrim(rtrim(number_format($minutes / 60, 2, '.', ''), '0'), '.');
        return \App\Core\Lang::locale() === 'tr' ? str_replace('.', ',', $h) : $h;
    }

    public static function decimal(): bool
    {
        return Settings::get('time.format', 'decimal') !== 'hm';
    }

    public static function hours(int $minutes): string
    {
        return number_format($minutes / 60, 1, \App\Core\Lang::locale() === 'tr' ? ',' : '.', '');
    }

    /** Kullanıcının kayıt girebileceği projeler: açık projeler, üyesi olduklarına öncelik */
    public static function projectOptions(int $userId): array
    {
        return DB::all(
            "SELECT p.id, p.number, p.name, p.billing_type, c.name AS client_name,
                    EXISTS (SELECT 1 FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?) AS is_member
             FROM projects p JOIN clients c ON c.id = p.client_id
             WHERE p.status = 'open' AND " . Access::projectWhere()[0] . "
             ORDER BY is_member DESC, c.name, p.number",
            array_merge([$userId], Access::projectWhere()[1])
        );
    }

    public static function week(int $userId, string $monday): array
    {
        return DB::all(
            'SELECT t.*, p.number AS project_number, p.name AS project_name, c.name AS client_name,
                    ph.name AS phase_name, ph.is_out_of_scope AS phase_oos
             FROM time_entries t
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = t.client_id
             LEFT JOIN project_phases ph ON ph.id = t.phase_id
             WHERE t.user_id = ? AND t.work_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
             ORDER BY t.work_date DESC, t.id DESC',
            [$userId, $monday, $monday]
        );
    }

    public static function forProject(int $projectId, int $limit = 20): array
    {
        return DB::all(
            'SELECT t.*, u.name AS user_name, ph.name AS phase_name, ph.is_out_of_scope AS phase_oos
             FROM time_entries t JOIN users u ON u.id = t.user_id
             LEFT JOIN project_phases ph ON ph.id = t.phase_id
             WHERE t.project_id = ? ORDER BY t.work_date DESC, t.id DESC LIMIT ' . (int) $limit,
            [$projectId]
        );
    }

    public static function projectTotals(int $projectId): array
    {
        return DB::one(
            'SELECT COALESCE(SUM(minutes), 0) AS minutes,
                    COALESCE(SUM(CASE WHEN is_billable = 1 THEN minutes END), 0) AS billable_minutes,
                    COALESCE(SUM(CASE WHEN is_billable = 1 THEN amount END), 0) AS amount
             FROM time_entries WHERE project_id = ?',
            [$projectId]
        );
    }

    public static function minutesInMonth(int $userId): int
    {
        return (int) DB::value(
            "SELECT COALESCE(SUM(minutes), 0) FROM time_entries
             WHERE user_id = ? AND work_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            [$userId]
        );
    }

    public static function recent(int $userId, int $limit = 6): array
    {
        return DB::all(
            'SELECT t.*, p.number AS project_number, p.name AS project_name
             FROM time_entries t JOIN projects p ON p.id = t.project_id
             WHERE t.user_id = ? ORDER BY t.work_date DESC, t.id DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM time_entries WHERE id = ?', [$id]);
    }

    /** Başkasının kaydı yalnızca time.all ile; dökümde kullanılmış kayıt hiç değiştirilemez. */
    public static function canModify(array $entry): bool
    {
        return $entry['statement_id'] === null
            && ((int) $entry['user_id'] === Auth::id() || Auth::can('time.all'));
    }

    /**
     * Kurşun: ücret sonradan girilir/değişirse faturalanmamış kayıtların ücreti ve tutarı yeniden çözülür.
     * $scope: user_id, client_id ya da project_id. Dökümdeki (statement_id dolu) kayıtlara dokunulmaz.
     * @return int değişen kayıt sayısı
     */
    public static function recalcRates(string $scope, int $id): int
    {
        if (!in_array($scope, ['user_id', 'client_id', 'project_id'], true)) {
            throw new \InvalidArgumentException($scope);
        }
        $rows = DB::all(
            "SELECT t.id, t.project_id, t.user_id, t.minutes, t.is_billable, t.rate, t.amount, p.billing_type
             FROM time_entries t JOIN projects p ON p.id = t.project_id
             WHERE t.$scope = ? AND t.statement_id IS NULL",
            [$id]
        );
        $cache = [];
        $changed = 0;
        foreach ($rows as $t) {
            $key = $t['project_id'] . ':' . $t['user_id'];
            $r = $cache[$key] ??= RateService::resolve((int) $t['project_id'], (int) $t['user_id']);
            $amount = ($t['is_billable'] && $t['billing_type'] === 'hourly' && $r['rate'] !== null)
                ? round($r['rate'] * $t['minutes'] / 60, 2)
                : null;
            $sameRate = ($t['rate'] === null ? null : (float) $t['rate']) === $r['rate'];
            $sameAmount = ($t['amount'] === null ? null : (float) $t['amount']) === $amount;
            if ($sameRate && $sameAmount) {
                continue;
            }
            DB::update('time_entries', ['rate' => $r['rate'], 'amount' => $amount, 'rate_source' => $r['source']], 'id = ?', [$t['id']]);
            $changed++;
        }
        return $changed;
    }

    public static function create(array $input, string $channel = 'web'): int
    {
        $data = self::validate($input, null);
        $data['created_by'] = Auth::id();
        $id = DB::insert('time_entries', $data);
        Audit::log('time.created', 'time_entry', $id, ['project_id' => $data['project_id'], 'minutes' => $data['minutes']], $channel);
        return $id;
    }

    public static function update(int $id, array $input, string $channel = 'web'): void
    {
        $entry = self::find($id);
        if (!$entry || !self::canModify($entry)) {
            throw new ValidationException(['entry' => __('time.locked')]);
        }
        $data = self::validate($input, $entry);
        DB::update('time_entries', $data, 'id = ?', [$id]);
        Audit::log('time.updated', 'time_entry', $id, $data, $channel);
    }

    public static function delete(int $id, string $channel = 'web'): void
    {
        $entry = self::find($id);
        if (!$entry || !self::canModify($entry)) {
            throw new ValidationException(['entry' => __('time.locked')]);
        }
        DB::query('DELETE FROM time_entries WHERE id = ?', [$id]);
        Audit::log('time.deleted', 'time_entry', $id, $entry, $channel);
    }

    // --- Sayaç ---

    public static function timer(int $userId): ?array
    {
        return DB::one(
            'SELECT t.*, p.number AS project_number, p.name AS project_name
             FROM timers t JOIN projects p ON p.id = t.project_id WHERE t.user_id = ?',
            [$userId]
        );
    }

    public static function startTimer(int $userId, int $projectId, ?string $description): void
    {
        if (!DB::value("SELECT id FROM projects WHERE id = ? AND status = 'open'", [$projectId]) || !Access::canSeeProject($projectId)) {
            throw new ValidationException(['timer_project_id' => __('validation.required')]);
        }
        DB::query(
            'REPLACE INTO timers (user_id, project_id, description, started_at) VALUES (?, ?, ?, NOW())',
            [$userId, $projectId, trim((string) $description) ?: null]
        );
    }

    /** Sayacı durdurur ve kayda çevirir; tarih sayacın başladığı gündür. */
    public static function stopTimer(int $userId, string $description, bool $billable): int
    {
        $timer = self::timer($userId) ?? throw new ValidationException(['timer' => __('time.no_timer')]);
        $minutes = max(1, (int) ceil((time() - strtotime($timer['started_at'])) / 60));

        $id = self::create([
            'user_id'     => $userId,
            'project_id'  => $timer['project_id'],
            'work_date'   => substr($timer['started_at'], 0, 10),
            'duration'    => (string) $minutes . 'dk',
            'description' => $description,
            'is_billable' => $billable ? 1 : 0,
        ]);
        DB::query('DELETE FROM timers WHERE user_id = ?', [$userId]);
        return $id;
    }

    public static function discardTimer(int $userId): void
    {
        DB::query('DELETE FROM timers WHERE user_id = ?', [$userId]);
    }

    private static function validate(array $in, ?array $current): array
    {
        $errors = [];

        // Başkası adına kayıt yalnızca time.all ile
        $userId = (int) ($in['user_id'] ?? ($current['user_id'] ?? Auth::id()));
        if ($userId !== Auth::id() && !Auth::can('time.all')) {
            $userId = (int) ($current['user_id'] ?? Auth::id());
        }

        $projectId = (int) ($in['project_id'] ?? 0);
        $project = DB::one('SELECT id, client_id, status, billing_type, currency FROM projects WHERE id = ?', [$projectId]);
        if ($project && !Access::canSeeProject($projectId) && (int) ($current['project_id'] ?? 0) !== $projectId) {
            $project = null;   // görmediği projeye kayıt giremez
        }
        // Kapalı projeye yeni kayıt girilmez; mevcut kaydın projesi aynı kaldıysa düzenlenebilir
        if (!$project || ($project['status'] !== 'open' && (int) ($current['project_id'] ?? 0) !== $projectId)) {
            $errors['project_id'] = __('time.project_required');
        }

        $date = (string) ($in['work_date'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['work_date'] = __('validation.date');
        } elseif ($date > date('Y-m-d')) {
            $errors['work_date'] = __('time.future_date');
        }

        $raw = self::parseDuration((string) ($in['duration'] ?? ''));
        if ($raw === null || $raw <= 0) {
            $errors['duration'] = __('time.invalid_duration');
        } elseif ($raw > 24 * 60) {
            $errors['duration'] = __('time.too_long');
        }

        $description = trim((string) ($in['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = __('time.description_required');
        }

        // Kurşun: aşama isteğe bağlı; seçildiyse bu projenin aktif (onaylı) aşaması olmalı.
        // Alan formda hiç yoksa (sayaç, Outlook) mevcut aşama korunur.
        $phaseId = array_key_exists('phase_id', $in)
            ? ((int) $in['phase_id'] ?: null)
            : (($current && (int) $current['project_id'] === $projectId) ? ($current['phase_id'] !== null ? (int) $current['phase_id'] : null) : null);
        if ($phaseId !== null && $project) {
            $ph = DB::one('SELECT is_optional, approved_at FROM project_phases WHERE id = ? AND project_id = ?', [$phaseId, $projectId]);
            $keeps = $current && (int) ($current['phase_id'] ?? 0) === $phaseId;
            if (!$ph || ($ph['is_optional'] && $ph['approved_at'] === null && !$keeps)) {
                $errors['phase_id'] = __('phases.invalid_choice');
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $minutes = round_minutes($raw);
        $billable = $project['billing_type'] === 'non_billable' ? false : !empty($in['is_billable']);

        // Ücret: yeni kayıtta veya proje/kişi değiştiyse yeniden çözülür, yoksa kayıttaki ücret korunur
        if (!$current || (int) $current['project_id'] !== $projectId || (int) $current['user_id'] !== $userId) {
            $r = RateService::resolve($projectId, $userId);
            $rate = $r['rate'];
            $source = $r['source'];
        } else {
            $rate = $current['rate'] !== null ? (float) $current['rate'] : null;
            $source = $current['rate_source'];
        }
        $amount = ($billable && $project['billing_type'] === 'hourly' && $rate !== null)
            ? round($rate * $minutes / 60, 2)
            : null;

        return [
            'user_id'     => $userId,
            'project_id'  => $projectId,
            'phase_id'    => $phaseId,
            'client_id'   => (int) $project['client_id'],
            'work_date'   => $date,
            'minutes'     => $minutes,
            'raw_minutes' => $raw,
            'description' => $description,
            'is_billable' => (int) $billable,
            'rate'        => $rate,
            'currency'    => $project['currency'],
            'amount'      => $amount,
            'rate_source' => $source,
        ];
    }
}
