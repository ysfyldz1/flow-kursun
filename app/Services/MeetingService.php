<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use DateTime;
use PDOException;

/**
 * Genel kurul bilgi formu. Ofis formu müvekkil şirket için açar, pay sahiplerini girer; pay sahipleri tek ortak
 * linkten adını seçip katılım şeklini bildirir (yanıtlanan isim kilitlenir). Vekaleten katılımda forma yüklenen
 * Word şablonundan doldurulmuş vekaletname üretilir. Form toplantı saatinde kendiliğinden kapanır.
 * Toplantı takvime "toplantı" olarak düşer; müvekkil portalında yanıt durumu görünür.
 */
final class MeetingService
{
    public const ATTENDANCE = ['in_person', 'proxy', 'representative'];

    /** Vekaletname şablonunda kullanılabilecek alanlar; açıklamaları lang'de "meetings.field_<ALAN>". */
    public const TEMPLATE_FIELDS = [
        'PAY_SAHIBI_AD', 'PAY_SAHIBI_KIMLIK_TURU', 'PAY_SAHIBI_KIMLIK_NO', 'PAY_SAHIBI_ADRES',
        'PAY_ADEDI', 'PAY_GRUBU', 'PAY_DETAY', 'VEKIL_AD', 'VEKIL_TC', 'VEKIL_ADRES',
        'SIRKET', 'TOPLANTI_TARIHI', 'TOPLANTI_SAATI',
    ];

    private const TR_MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    private const SELECT = 'SELECT m.*, c.name AS client_name, c.code AS client_code, p.number AS project_number, p.name AS project_name,
                                   (SELECT COUNT(*) FROM meeting_shareholders s WHERE s.meeting_id = m.id) AS shareholder_count,
                                   (SELECT COUNT(*) FROM meeting_responses r WHERE r.meeting_id = m.id) AS response_count
                            FROM general_meetings m
                            JOIN clients c ON c.id = m.client_id
                            LEFT JOIN projects p ON p.id = m.project_id';

    public static function find(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE m.id = ?', [$id]);
    }

    public static function findByToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{32}$/', $token) ? DB::one(self::SELECT . ' WHERE m.token = ?', [$token]) : null;
    }

    /** when: upcoming (bugünden sonra) | past */
    public static function list(string $when): array
    {
        [$visible, $params] = Access::clientWhere();
        return DB::all(
            self::SELECT . " WHERE $visible AND " . ($when === 'past' ? 'm.meeting_at < NOW() ORDER BY m.meeting_at DESC' : 'm.meeting_at >= NOW() ORDER BY m.meeting_at'),
            $params
        );
    }

    public static function forClient(int $clientId): array
    {
        return DB::all(self::SELECT . ' WHERE m.client_id = ? ORDER BY m.meeting_at DESC', [$clientId]);
    }

    /** Pay sahipleri ve (varsa) yanıtları. */
    public static function shareholders(int $meetingId): array
    {
        return DB::all(
            'SELECT s.*, r.id AS response_id, r.attendance, r.proxy_name, r.proxy_tc, r.proxy_address, r.proxy_is_default,
                    r.doc_token, r.created_at AS responded_at
             FROM meeting_shareholders s LEFT JOIN meeting_responses r ON r.shareholder_id = s.id
             WHERE s.meeting_id = ? ORDER BY s.sort_order, s.id',
            [$meetingId]
        );
    }

    /** Yanıt dağılımı: [in_person => n, proxy => n, representative => n, pending => n] */
    public static function summary(array $shareholders): array
    {
        $out = array_fill_keys([...self::ATTENDANCE, 'pending'], 0);
        foreach ($shareholders as $s) {
            $out[$s['attendance'] ?? 'pending']++;
        }
        return $out;
    }

    public static function accepting(array $m): bool
    {
        return (bool) $m['is_open'] && strtotime($m['meeting_at']) > time();
    }

    public static function publicUrl(array $m): string
    {
        return rtrim((string) config('app.url'), '/') . '/gk/' . $m['token'];
    }

    public static function isDocx(array $m): bool
    {
        return (bool) $m['proxy_doc_file'] && str_ends_with($m['proxy_doc_file'], '.docx');
    }

    public static function docPath(?string $file): ?string
    {
        $path = BASE_PATH . '/storage/uploads/meetings/' . basename((string) $file);
        return $file && is_file($path) ? $path : null;
    }

    // --- Kayıt -------------------------------------------------------------------------------

    public static function save(?int $id, array $in, ?array $file, string $channel = 'web'): int
    {
        $current = $id ? self::find($id) : null;
        $data = self::validate($in, $current);
        $shareholders = self::parseLines((string) ($in['shareholders'] ?? ''));
        if (!$shareholders) {
            throw new ValidationException(['shareholders' => __('meetings.shareholders_required')]);
        }

        $oldDoc = null;
        if ($new = self::storeDoc($file)) {
            [$data['proxy_doc_file'], $data['proxy_doc_name']] = $new;
            $oldDoc = $current['proxy_doc_file'] ?? null;
        } elseif (!empty($in['remove_doc'])) {
            $data['proxy_doc_file'] = $data['proxy_doc_name'] = null;
            $oldDoc = $current['proxy_doc_file'] ?? null;
        }

        $id = DB::transaction(function () use ($id, $data, $shareholders) {
            if ($id) {
                DB::update('general_meetings', $data, 'id = ?', [$id]);
            } else {
                $id = DB::insert('general_meetings', $data + ['token' => bin2hex(random_bytes(16)), 'created_by' => Auth::id()]);
            }
            self::syncShareholders($id, $shareholders);
            return $id;
        });
        if ($oldDoc && ($path = self::docPath($oldDoc))) {
            unlink($path);
        }
        Audit::log($current ? 'meeting.updated' : 'meeting.created', 'meeting', $id, ['client_id' => $data['client_id'], 'meeting_at' => $data['meeting_at']], $channel);
        self::syncEvent($id);
        return $id;
    }

    public static function toggleOpen(int $id): void
    {
        DB::query('UPDATE general_meetings SET is_open = 1 - is_open WHERE id = ?', [$id]);
        Audit::log('meeting.toggled', 'meeting', $id);
    }

    public static function resetResponse(int $meetingId, int $shareholderId): void
    {
        DB::query('DELETE FROM meeting_responses WHERE meeting_id = ? AND shareholder_id = ?', [$meetingId, $shareholderId]);
        Audit::log('meeting.response_reset', 'meeting', $meetingId, ['shareholder_id' => $shareholderId]);
    }

    public static function delete(int $id): void
    {
        $m = self::find($id);
        DB::query('DELETE FROM general_meetings WHERE id = ?', [$id]);
        if ($m['event_id'] && CalendarService::find((int) $m['event_id'])) {
            CalendarService::delete((int) $m['event_id']);
        }
        if ($path = self::docPath($m['proxy_doc_file'])) {
            unlink($path);
        }
        Audit::log('meeting.deleted', 'meeting', $id, ['client_id' => $m['client_id'], 'meeting_at' => $m['meeting_at']]);
    }

    /** Toplantıyı takvime yansıtır: ilk kayıtta oluşturan kişi katılımcıdır, sonra takvimden eklenenler korunur. */
    private static function syncEvent(int $id): void
    {
        $m = self::find($id);
        $event = $m['event_id'] ? CalendarService::find((int) $m['event_id']) : null;
        $input = [
            'type'       => 'meeting',
            'title'      => __('meetings.event_title', ['client' => $m['client_name']]),
            'project_id' => $m['project_id'],
            'date'       => substr($m['meeting_at'], 0, 10),
            'start_time' => substr($m['meeting_at'], 11, 5),
            'location'   => $m['location'],
            'notes'      => $m['agenda'],
            'attendees'  => $event ? array_column($event['attendees'], 'user_id') : [Auth::id()],
        ];
        if ($event) {
            CalendarService::update((int) $event['id'], $input + ['end_time' => $event['ends_at'] ? substr($event['ends_at'], 11, 5) : '']);
        } else {
            DB::update('general_meetings', ['event_id' => CalendarService::create($input)], 'id = ?', [$id]);
        }
    }

    /** Ad'a göre eşler: mevcut pay sahibinin yanıtı korunur, listeden çıkan silinir. */
    private static function syncShareholders(int $meetingId, array $rows): void
    {
        $existing = [];
        foreach (DB::all('SELECT id, full_name FROM meeting_shareholders WHERE meeting_id = ?', [$meetingId]) as $r) {
            $existing[$r['full_name']] = (int) $r['id'];
        }
        foreach ($rows as $i => $s) {
            $s['sort_order'] = $i;
            if (isset($existing[$s['full_name']])) {
                DB::update('meeting_shareholders', $s, 'id = ?', [$existing[$s['full_name']]]);
                unset($existing[$s['full_name']]);
            } else {
                DB::insert('meeting_shareholders', $s + ['meeting_id' => $meetingId]);
            }
        }
        if ($existing) {
            DB::query('DELETE FROM meeting_shareholders WHERE id IN (' . implode(',', array_values($existing)) . ')');
        }
    }

    private static function validate(array $in, ?array $current): array
    {
        $errors = [];
        $clean = fn ($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        $clientId = (int) ($current['client_id'] ?? $in['client_id'] ?? 0);   // müvekkil sonradan değişmez
        if (!DB::value('SELECT id FROM clients WHERE id = ?', [$clientId]) || (!$current && !Access::canSeeClient($clientId))) {
            $errors['client_id'] = __('validation.required');
        }
        $projectId = (int) ($in['project_id'] ?? 0) ?: null;
        if ($projectId && (!DB::value('SELECT id FROM projects WHERE id = ? AND client_id = ?', [$projectId, $clientId]) || (!Access::canSeeProject($projectId) && (int) ($current['project_id'] ?? 0) !== $projectId))) {
            $errors['project_id'] = __('meetings.project_mismatch');
        }
        $date = (string) ($in['date'] ?? '');
        $time = (string) ($in['time'] ?? '');
        $at = DateTime::createFromFormat('!Y-m-d H:i', "$date $time");
        if (!$at || $at->format('Y-m-d H:i') !== "$date $time") {
            $errors['date'] = __('validation.date');
        }
        $agenda = trim((string) ($in['agenda'] ?? ''));
        if ($agenda === '') {
            $errors['agenda'] = __('validation.required');
        }
        $proxy = [
            'default_proxy_name'    => $clean($in['default_proxy_name'] ?? ''),
            'default_proxy_tc'      => preg_replace('/\D/', '', (string) ($in['default_proxy_tc'] ?? '')),
            'default_proxy_address' => $clean($in['default_proxy_address'] ?? ''),
        ];
        if (implode('', $proxy) !== '' && (mb_strlen($proxy['default_proxy_name']) < 3 || !preg_match('/^[1-9]\d{10}$/', $proxy['default_proxy_tc']) || $proxy['default_proxy_address'] === '')) {
            $errors['default_proxy_name'] = __('meetings.default_proxy_incomplete');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return [
            'client_id'  => $clientId,
            'project_id' => $projectId,
            'meeting_at' => $at->format('Y-m-d H:i:00'),
            'location'   => $clean($in['location'] ?? '') ?: null,
            'agenda'     => $agenda,
        ] + array_map(fn ($v) => $v !== '' ? $v : null, $proxy);
    }

    /** Vekaletname: .docx şablon (doldurulur) ya da .pdf örnek (yalnız gösterilir). */
    private static function storeDoc(?array $file): ?array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $head = $file['error'] === UPLOAD_ERR_OK ? (string) file_get_contents($file['tmp_name'], false, null, 0, 4) : '';
        $ok = ($ext === 'docx' && $head === "PK\x03\x04") || ($ext === 'pdf' && $head === '%PDF');
        if (!$ok || $file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['proxy_doc' => __('meetings.doc_invalid')]);
        }
        $dir = BASE_PATH . '/storage/uploads/meetings';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . ".$ext";
        move_uploaded_file($file['tmp_name'], "$dir/$name");
        return [$name, mb_substr(basename((string) $file['name']), 0, 255)];
    }

    // --- Pay sahibi listesi -------------------------------------------------------------------

    /** Satır biçimi: "Ad Soyad | Kimlik No | Pay Adedi | Pay Grubu | Adres" (ad dışındakiler isteğe bağlı). */
    public static function parseLines(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $parts = array_map(fn ($p) => trim(preg_replace('/\s+/u', ' ', $p)), explode('|', $line));
            $name = mb_substr($parts[0], 0, 255);
            if ($name === '' || isset($rows[$name])) {
                continue;
            }
            $idNo = preg_replace('/\D/', '', $parts[1] ?? '');
            $rows[$name] = [
                'full_name'   => $name,
                'id_no'       => in_array(strlen($idNo), [10, 11], true) ? $idNo : null,
                'share_count' => mb_substr($parts[2] ?? '', 0, 255) ?: null,
                'share_group' => mb_substr($parts[3] ?? '', 0, 255) ?: null,
                'address'     => mb_substr(implode(', ', array_filter(array_slice($parts, 4))), 0, 500) ?: null,
            ];
        }
        return array_values($rows);
    }

    public static function line(array $s): string
    {
        $parts = [$s['full_name'], $s['id_no'] ?? '', $s['share_count'] ?? '', $s['share_group'] ?? '', $s['address'] ?? ''];
        while (count($parts) > 1 && end($parts) === '') {
            array_pop($parts);
        }
        return implode(' | ', $parts);
    }

    /**
     * Excel/CSV satırlarını textarea satırlarına çevirir. İlk satır başlıksa sütunlar adından tanınır
     * (ad/unvan, tc/vkn/kimlik, pay/adet, grup, adres); değilse sıra: ad, kimlik, pay, grup, adres.
     */
    public static function importLines(array $rows): array
    {
        $map = ['name' => 0, 'id' => 1, 'shares' => 2, 'group' => 3, 'address' => 4];
        $patterns = [
            'id'      => '/(tc|t\.c|vkn|vergi|kimlik|id)/iu',
            'group'   => '/(grup|group|class)/iu',
            'shares'  => '/(pay|adet|share|hisse)/iu',
            'address' => '/(adres|address)/iu',
            'name'    => '/(ad|unvan|isim|name|ortak|shareholder)/iu',
        ];
        $header = array_map(fn ($c) => trim((string) $c), $rows[0] ?? []);
        $found = [];
        foreach ($header as $i => $cell) {
            foreach ($patterns as $key => $re) {
                if (!isset($found[$key]) && $cell !== '' && !preg_match('/^\d/', $cell) && preg_match($re, $cell)) {
                    $found[$key] = $i;
                    break;
                }
            }
        }
        if (isset($found['name'])) {
            $map = $found + array_fill_keys(array_keys($map), -1);
            array_shift($rows);
        }
        $cell = fn (array $r, string $k) => $map[$k] >= 0 ? trim(str_replace('|', ' ', (string) ($r[$map[$k]] ?? ''))) : '';
        $lines = [];
        foreach ($rows as $r) {
            if ($cell($r, 'name') === '') {
                continue;
            }
            $lines[] = self::line([
                'full_name'   => $cell($r, 'name'),
                'id_no'       => preg_replace('/\D/', '', $cell($r, 'id')),
                'share_count' => preg_replace('/\.0+$/', '', $cell($r, 'shares')),
                'share_group' => $cell($r, 'group'),
                'address'     => $cell($r, 'address'),
            ]);
        }
        return $lines;
    }

    /** "1250" → "1.250"; grup grup girilmişse ("95874, 1110") her biri ayrı biçimlenir. */
    public static function formatShares(?string $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(\s*,\s*\d+)*$/', $value)) {
            return $value;
        }
        return implode(', ', array_map(fn ($n) => number_format((int) $n, 0, ',', '.'), preg_split('/\s*,\s*/', $value)));
    }

    /** "95874, 1110" + "A, B" → "95.874 adet A grubu, 1.110 adet B grubu" (vekaletname Türkçe olduğu için Türkçe). */
    public static function shareBreakdown(?string $count, ?string $group): string
    {
        $count = trim((string) $count);
        $group = trim((string) $group);
        if ($count === '') {
            return '';
        }
        $counts = preg_split('/\s*,\s*/', $count);
        $groups = $group === '' ? [] : preg_split('/\s*,\s*/', $group);
        if (count($groups) > 1 && count($groups) === count($counts)) {
            return implode(', ', array_map(fn ($c, $g) => self::formatShares($c) . " adet $g grubu", $counts, $groups));
        }
        return self::formatShares($count) . ' adet' . ($group !== '' ? " $group grubu" : '');
    }

    // --- Herkese açık form --------------------------------------------------------------------

    /** Pay sahibinin yanıtı; vekaletenda doldurulmuş vekaletname için doc_token döner. */
    public static function respond(array $m, array $in, ?string $ip): ?string
    {
        $clean = fn ($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        $shareholderId = (int) ($in['shareholder_id'] ?? 0);
        $attendance = (string) ($in['attendance'] ?? '');
        $hasDefault = (bool) $m['default_proxy_name'];
        $isProxy = $attendance === 'proxy';
        $useDefault = $isProxy && $hasDefault && ($in['proxy_choice'] ?? '') === 'default';
        $proxyName = $clean($in['proxy_name'] ?? '');
        $proxyTc = preg_replace('/\D/', '', (string) ($in['proxy_tc'] ?? ''));
        $proxyAddress = mb_substr($clean($in['proxy_address'] ?? ''), 0, 500);
        $shIdNo = preg_replace('/\D/', '', (string) ($in['sh_id_no'] ?? ''));
        $shShares = preg_replace('/[^\d,]/', '', (string) ($in['sh_share_count'] ?? ''));
        $shGroup = mb_substr($clean($in['sh_share_group'] ?? ''), 0, 255);

        $sh = DB::one(
            'SELECT s.*, r.id AS response_id FROM meeting_shareholders s LEFT JOIN meeting_responses r ON r.shareholder_id = s.id
             WHERE s.id = ? AND s.meeting_id = ?',
            [$shareholderId, $m['id']]
        );
        $error = match (true) {
            !$sh                                                                   => ['shareholder_id', 'gk.err_choose_name'],
            (bool) $sh['response_id']                                              => ['shareholder_id', 'gk.err_already'],
            !in_array($attendance, self::ATTENDANCE, true)                         => ['attendance', 'gk.err_attendance'],
            // Link herkese açık: vekaletname üretmeden önce pay sahibinin kimliği kayıtla eşleşmeli
            $isProxy && !preg_match('/^[1-9]\d{9,10}$/', $shIdNo)                  => ['sh_id_no', 'gk.err_id_format'],
            $isProxy && $sh['id_no'] && $sh['id_no'] !== $shIdNo                   => ['sh_id_no', 'gk.err_id_mismatch'],
            $isProxy && !$sh['share_count'] && $shShares === ''                    => ['sh_share_count', 'gk.err_shares'],
            $isProxy && $hasDefault && !in_array($in['proxy_choice'] ?? '', ['default', 'custom'], true) => ['proxy_choice', 'gk.err_proxy_choice'],
            $isProxy && !$useDefault && mb_strlen($proxyName) < 3                  => ['proxy_name', 'gk.err_proxy_name'],
            $isProxy && !$useDefault && !preg_match('/^[1-9]\d{10}$/', $proxyTc)   => ['proxy_tc', 'gk.err_proxy_tc'],
            $isProxy && !$useDefault && mb_strlen($proxyAddress) < 5               => ['proxy_address', 'gk.err_proxy_address'],
            default                                                                => null,
        };
        if ($error) {
            throw new ValidationException([$error[0] => __($error[1])]);
        }

        if ($useDefault) {
            [$proxyName, $proxyTc, $proxyAddress] = [$m['default_proxy_name'], $m['default_proxy_tc'], $m['default_proxy_address']];
        }
        $docToken = $isProxy ? bin2hex(random_bytes(16)) : null;
        try {
            DB::transaction(function () use ($m, $sh, $attendance, $isProxy, $useDefault, $proxyName, $proxyTc, $proxyAddress, $shIdNo, $shShares, $shGroup, $docToken, $ip) {
                // Pay sahibinin eksik bilgileri tamamlanır; listeden gelen bilgilerin üzerine yazılmaz
                if ($isProxy) {
                    DB::query(
                        'UPDATE meeting_shareholders SET id_no = COALESCE(id_no, ?), share_count = COALESCE(share_count, ?), share_group = COALESCE(share_group, ?) WHERE id = ?',
                        [$shIdNo, $shShares ?: null, $shGroup ?: null, $sh['id']]
                    );
                }
                DB::insert('meeting_responses', [
                    'meeting_id'       => $m['id'],
                    'shareholder_id'   => $sh['id'],
                    'attendance'       => $attendance,
                    'proxy_name'       => $isProxy ? $proxyName : null,
                    'proxy_tc'         => $isProxy ? $proxyTc : null,
                    'proxy_address'    => $isProxy ? $proxyAddress : null,
                    'proxy_is_default' => (int) $useDefault,
                    'doc_token'        => $docToken,
                    'ip_address'       => $ip,
                ]);
            });
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            throw new ValidationException(['shareholder_id' => __('gk.err_already')]);
        }
        Audit::log('meeting.responded', 'meeting', (int) $m['id'], ['shareholder' => $sh['full_name'], 'attendance' => $attendance], 'portal');
        return $docToken;
    }

    // --- Vekaletname --------------------------------------------------------------------------

    /** Doldurulmuş vekaletnameyi geçici dosyaya yazar; [yol, indirme adı] döner. */
    public static function filledProxy(array $m, array $shareholder): ?array
    {
        $src = self::isDocx($m) ? self::docPath($m['proxy_doc_file']) : null;
        if (!$src || $shareholder['attendance'] !== 'proxy') {
            return null;
        }
        $dest = tempnam(sys_get_temp_dir(), 'gk') . '.docx';
        if (!DocxTemplate::fill($src, $dest, self::templateValues($m, $shareholder))) {
            return null;
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $shareholder['full_name'])), '-');
        return [$dest, "Vekaletname-$slug.docx"];
    }

    private static function templateValues(array $m, array $s): array
    {
        $ts = strtotime($m['meeting_at']);
        $idNo = (string) ($s['id_no'] ?? '');
        return [
            'PAY_SAHIBI_AD'          => $s['full_name'],
            'PAY_SAHIBI_KIMLIK_TURU' => strlen($idNo) === 10 ? 'Vergi Kimlik Numaralı' : 'T.C. Kimlik Numaralı',
            'PAY_SAHIBI_KIMLIK_NO'   => $idNo,
            'PAY_SAHIBI_ADRES'       => $s['address'] ?? '',
            'PAY_ADEDI'              => self::formatShares($s['share_count']),
            'PAY_GRUBU'              => $s['share_group'] ?? '',
            'PAY_DETAY'              => self::shareBreakdown($s['share_count'], $s['share_group']),
            'VEKIL_AD'               => $s['proxy_name'] ?? '',
            'VEKIL_TC'               => $s['proxy_tc'] ?? '',
            'VEKIL_ADRES'            => $s['proxy_address'] ?? '',
            'SIRKET'                 => $m['client_name'],
            'TOPLANTI_TARIHI'        => date('j', $ts) . ' ' . self::TR_MONTHS[date('n', $ts) - 1] . ' ' . date('Y', $ts),
            'TOPLANTI_SAATI'         => date('H:i', $ts),
        ];
    }

    /** Yanıtlar CSV (UTF-8 BOM, ';') — raporlardaki Excel biçimiyle aynı. */
    public static function csv(array $m): void
    {
        $name = 'genel-kurul-' . trim(preg_replace('/[^a-z0-9]+/i', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $m['client_name'])), '-') . '-' . substr($m['meeting_at'], 0, 10) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [__('meetings.shareholder'), __('meetings.id_no'), __('meetings.shares'), __('meetings.share_group'), __('meetings.address'),
            __('meetings.attendance'), __('meetings.proxy'), __('meetings.proxy_tc'), __('meetings.proxy_address'), __('meetings.responded_at')], ';');
        foreach (self::shareholders((int) $m['id']) as $s) {
            fputcsv($out, [$s['full_name'], $s['id_no'], $s['share_count'], $s['share_group'], $s['address'],
                $s['attendance'] ? __('meetings.att_' . $s['attendance']) : __('meetings.pending'),
                $s['proxy_name'], $s['proxy_tc'], $s['proxy_address'], $s['responded_at'] ? format_date($s['responded_at'], true) : ''], ';');
        }
        fclose($out);
    }
}
