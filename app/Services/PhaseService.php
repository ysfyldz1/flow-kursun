<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;

/**
 * Kurşun: proje aşamaları.
 * Aşama bedeli bir tavandır; aşamaya girilen zaman tutarı bedelden düşer.
 * "Kapsam dışı" grubu her aşamalı projede bir tane olur, silinmez, bedeli yoktur.
 */
final class PhaseService
{
    public const UNITS = ['day', 'week', 'month'];

    /** Projenin aşamaları, harcanan/kalan ve süre bilgisiyle. Kapsam dışı en sonda. */
    public static function forProject(int $projectId): array
    {
        $project = DB::one('SELECT opened_at, phase_warn_pct FROM projects WHERE id = ?', [$projectId]);
        $rows = DB::all(
            'SELECT ph.*,
                    COALESCE(SUM(t.minutes), 0) AS minutes,
                    COALESCE(SUM(CASE WHEN t.is_billable = 1 THEN t.amount END), 0) AS spent,
                    COUNT(t.id) AS entry_count
             FROM project_phases ph
             LEFT JOIN time_entries t ON t.phase_id = ph.id
             WHERE ph.project_id = ?
             GROUP BY ph.id
             ORDER BY ph.is_out_of_scope, ph.position, ph.id',
            [$projectId]
        );
        return array_map(fn ($r) => self::decorate($r, $project), $rows);
    }

    public static function find(int $projectId, int $id): ?array
    {
        foreach (self::forProject($projectId) as $ph) {
            if ((int) $ph['id'] === $id) {
                return $ph;
            }
        }
        return null;
    }

    /** Zaman girişinde seçilebilecek aşamalar: onaylanmamış opsiyoneller hariç. */
    public static function selectable(int $projectId): array
    {
        return array_values(array_filter(self::forProject($projectId), fn ($ph) => $ph['is_active']));
    }

    /**
     * Zaman formu için: aşaması olan projelerin aşamaları, istemcide seçim ve uyarı göstermek üzere.
     * Tutarlar yalnız ücret yetkisi olana gider; oran herkese.
     * @return array<int, list<array>> project_id => aşamalar
     */
    public static function optionsForProjects(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        if (!$projectIds) {
            return [];
        }
        $with = array_map('intval', array_column(DB::all(
            'SELECT DISTINCT project_id FROM project_phases WHERE project_id IN (' . implode(',', array_fill(0, count($projectIds), '?')) . ')',
            $projectIds
        ), 'project_id'));
        $rates = Auth::can('rates.view');
        $out = [];
        foreach ($with as $pid) {
            $currency = (string) DB::value('SELECT currency FROM projects WHERE id = ?', [$pid]);
            $no = 0;
            foreach (self::forProject($pid) as $ph) {
                $out[$pid][] = [
                    'id'        => (int) $ph['id'],
                    'name'      => ($ph['is_out_of_scope'] ? '' : ++$no . '. ') . $ph['name'],
                    'active'    => $ph['is_active'],
                    'oos'       => (bool) $ph['is_out_of_scope'],
                    'overdue'   => $ph['is_overdue'],
                    'completed' => (bool) $ph['completed_at'],
                    'state'     => $ph['budget_state'],
                    'pct'       => $ph['used_pct'],
                    'remaining' => $rates && $ph['remaining'] !== null ? format_money((float) $ph['remaining'], $currency) : null,
                    'over'      => $rates && $ph['over'] ? format_money($ph['over'], $currency) : null,
                ];
            }
        }
        return $out;
    }

    /** Kayıt sonrası gösterilecek bütçe uyarısı (yoksa null). */
    public static function warningFor(int $phaseId): ?string
    {
        $projectId = (int) DB::value('SELECT project_id FROM project_phases WHERE id = ?', [$phaseId]);
        $ph = $projectId ? self::find($projectId, $phaseId) : null;
        if (!$ph || !$ph['budget_state'] || $ph['budget_state'] === 'ok') {
            return null;
        }
        return $ph['budget_state'] === 'over'
            ? __('phases.saved_over', ['name' => $ph['name']])
            : __('phases.saved_warn', ['name' => $ph['name'], 'pct' => $ph['used_pct']]);
    }

    public static function hasPhases(int $projectId): bool
    {
        return (bool) DB::value('SELECT 1 FROM project_phases WHERE project_id = ? LIMIT 1', [$projectId]);
    }

    /** Süre, kalan bedel, uyarı durumu gibi türetilen alanlar. */
    private static function decorate(array $r, ?array $project): array
    {
        if ($r['is_out_of_scope']) {
            $r['name'] = __('phases.out_of_scope');
        }
        $r['scope_items'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $r['scope']))));
        $r['is_active'] = !$r['is_optional'] || $r['approved_at'] !== null;
        $r['start'] = $r['start_date'] ?: ($project['opened_at'] ?? null);

        // Bitiş = başlangıç + süre. Tamamlanmamış ve bitiş geçmişse "süre doldu".
        $r['deadline'] = null;
        if ($r['duration_value'] && $r['duration_unit'] && $r['start']) {
            $d = new \DateTime($r['start']);
            $d->modify('+' . (int) $r['duration_value'] . ' ' . $r['duration_unit']);
            $r['deadline'] = $d->format('Y-m-d');
        }
        $r['is_overdue'] = $r['deadline'] && !$r['completed_at'] && $r['is_active'] && $r['deadline'] < date('Y-m-d');

        // Bütçe: tavan bedel varsa harcanan oranı ve aşım
        $fee = $r['fee'] !== null ? (float) $r['fee'] : null;
        $spent = (float) $r['spent'];
        $warn = (int) ($project['phase_warn_pct'] ?? 80);
        $r['remaining'] = $fee !== null ? round($fee - $spent, 2) : null;
        $r['used_pct'] = $fee ? (int) round($spent / $fee * 100) : null;
        $r['over'] = $fee !== null && $spent > $fee ? round($spent - $fee, 2) : 0.0;
        $r['budget_state'] = match (true) {
            $fee === null || $fee <= 0 => null,
            $spent > $fee              => 'over',
            $r['used_pct'] >= $warn    => 'warn',
            default                    => 'ok',
        };
        return $r;
    }

    public static function create(int $projectId, array $input): int
    {
        $data = self::validate($projectId, $input, null);
        return DB::transaction(function () use ($projectId, $data) {
            self::ensureOutOfScope($projectId);
            $data['project_id'] = $projectId;
            $data['position'] = (int) DB::value('SELECT COALESCE(MAX(position), 0) + 1 FROM project_phases WHERE project_id = ? AND is_out_of_scope = 0', [$projectId]);
            $data['created_by'] = Auth::id();
            $id = DB::insert('project_phases', $data);
            Audit::log('phase.created', 'project', $projectId, ['phase_id' => $id, 'name' => $data['name']]);
            return $id;
        });
    }

    public static function update(int $projectId, int $id, array $input): void
    {
        $current = self::row($projectId, $id) ?? abort(404);
        $data = self::validate($projectId, $input, $current);
        DB::update('project_phases', $data, 'id = ?', [$id]);
        Audit::log('phase.updated', 'project', $projectId, ['phase_id' => $id] + $data);
    }

    /** Kayıt girilmiş aşama silinmez; önce kayıtlar taşınmalı. Kapsam dışı silinmez. */
    public static function delete(int $projectId, int $id): void
    {
        $ph = self::row($projectId, $id) ?? abort(404);
        if ($ph['is_out_of_scope']) {
            throw new ValidationException(['phase' => __('phases.cannot_delete_oos')]);
        }
        if (DB::value('SELECT 1 FROM time_entries WHERE phase_id = ? LIMIT 1', [$id])) {
            throw new ValidationException(['phase' => __('phases.cannot_delete_used')]);
        }
        DB::transaction(function () use ($projectId, $id, $ph) {
            DB::query('DELETE FROM project_phases WHERE id = ?', [$id]);
            // Son gerçek aşama gittiyse boş kapsam dışı grubu da kalkar
            if (!DB::value('SELECT 1 FROM project_phases WHERE project_id = ? AND is_out_of_scope = 0 LIMIT 1', [$projectId])
                && !DB::value('SELECT 1 FROM time_entries t JOIN project_phases ph ON ph.id = t.phase_id WHERE ph.project_id = ? LIMIT 1', [$projectId])) {
                DB::query('DELETE FROM project_phases WHERE project_id = ? AND is_out_of_scope = 1', [$projectId]);
            }
            Audit::log('phase.deleted', 'project', $projectId, ['phase_id' => $id, 'name' => $ph['name']]);
        });
    }

    public static function move(int $projectId, int $id, int $dir): void
    {
        $ph = self::row($projectId, $id) ?? abort(404);
        if ($ph['is_out_of_scope']) {
            return;
        }
        $ids = array_map('intval', array_column(DB::all('SELECT id FROM project_phases WHERE project_id = ? AND is_out_of_scope = 0 ORDER BY position, id', [$projectId]), 'id'));
        $i = array_search($id, $ids, true);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || !isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $pos => $pid) {
                DB::update('project_phases', ['position' => $pos + 1], 'id = ?', [$pid]);
            }
        });
    }

    public static function approve(int $projectId, int $id, bool $approved): void
    {
        $ph = self::row($projectId, $id) ?? abort(404);
        if (!$ph['is_optional']) {
            return;
        }
        if (!$approved && DB::value('SELECT 1 FROM time_entries WHERE phase_id = ? LIMIT 1', [$id])) {
            throw new ValidationException(['phase' => __('phases.cannot_unapprove_used')]);
        }
        DB::update('project_phases', [
            'approved_at' => $approved ? date('Y-m-d H:i:s') : null,
            'approved_by' => $approved ? Auth::id() : null,
        ], 'id = ?', [$id]);
        Audit::log($approved ? 'phase.approved' : 'phase.unapproved', 'project', $projectId, ['phase_id' => $id]);
    }

    public static function complete(int $projectId, int $id, bool $done): void
    {
        $ph = self::row($projectId, $id) ?? abort(404);
        if ($ph['is_out_of_scope']) {
            return;
        }
        DB::update('project_phases', ['completed_at' => $done ? date('Y-m-d') : null], 'id = ?', [$id]);
        Audit::log($done ? 'phase.completed' : 'phase.reopened', 'project', $projectId, ['phase_id' => $id]);
    }

    public static function setWarnPct(int $projectId, mixed $raw): void
    {
        $pct = (int) $raw;
        if ($pct < 1 || $pct > 100) {
            throw new ValidationException(['phase_warn_pct' => __('validation.number')]);
        }
        DB::update('projects', ['phase_warn_pct' => $pct], 'id = ?', [$projectId]);
    }

    /** Projede kapsam dışı grubu yoksa açar, kimliğini döner. */
    public static function ensureOutOfScope(int $projectId): int
    {
        $id = DB::value('SELECT id FROM project_phases WHERE project_id = ? AND is_out_of_scope = 1', [$projectId]);
        if ($id) {
            return (int) $id;
        }
        return DB::insert('project_phases', [
            'project_id'      => $projectId,
            'position'        => 0,
            'name'            => __('phases.out_of_scope'),
            'is_out_of_scope' => 1,
            'created_by'      => Auth::id(),
        ]);
    }

    private static function row(int $projectId, int $id): ?array
    {
        return DB::one('SELECT * FROM project_phases WHERE id = ? AND project_id = ?', [$id, $projectId]);
    }

    private static function validate(int $projectId, array $in, ?array $current): array
    {
        $errors = [];
        $oos = $current && $current['is_out_of_scope'];
        $data = [
            'name'  => trim((string) ($in['name'] ?? '')),
            'scope' => trim(str_replace("\r\n", "\n", (string) ($in['scope'] ?? ''))) ?: null,
        ];
        if ($data['name'] === '') {
            $errors['name'] = __('validation.required');
        }

        if (!$oos) {
            $duration = trim((string) ($in['duration_value'] ?? ''));
            $data += [
                'duration_value' => $duration === '' ? null : (int) $duration,
                'duration_unit'  => $duration === '' ? null : (string) ($in['duration_unit'] ?? 'month'),
                'start_date'     => trim((string) ($in['start_date'] ?? '')) ?: null,
                'is_optional'    => (int) !empty($in['is_optional']),
            ];
            if ($duration !== '' && (!ctype_digit($duration) || (int) $duration < 1 || (int) $duration > 999)) {
                $errors['duration_value'] = __('validation.number');
            }
            if ($data['duration_unit'] !== null && !in_array($data['duration_unit'], self::UNITS, true)) {
                $errors['duration_value'] = __('validation.required');
            }
            if ($data['start_date'] !== null) {
                $d = \DateTime::createFromFormat('Y-m-d', $data['start_date']);
                if (!$d || $d->format('Y-m-d') !== $data['start_date']) {
                    $errors['start_date'] = __('validation.date');
                }
            }
            // Opsiyonel işareti kalkarsa onay anlamını yitirir
            if (!$data['is_optional']) {
                $data['approved_at'] = null;
                $data['approved_by'] = null;
            }
            // Bedel yalnız ücret yetkisi olanca değişir
            if (Auth::can('rates.view')) {
                $fee = parse_money($in['fee'] ?? '');
                if ($fee === false || ($fee !== null && $fee < 0)) {
                    $errors['fee'] = __('validation.number');
                }
                $data['fee'] = $fee === false ? null : $fee;
            }
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
        return $data;
    }
}
