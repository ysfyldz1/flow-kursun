<?php

declare(strict_types=1);

namespace Modules\Meetings;

use App\Core\DB;
use App\Core\Lang;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\MeetingService;

/** Pay sahiplerinin oturum açmadan doldurduğu genel kurul bilgi formu (/gk/{token}). */
final class PublicMeetingController
{
    public function show(string $token): void
    {
        $m = $this->load($token);
        $state = !$m ? 'notfound' : (!MeetingService::accepting($m) ? 'closed' : (isset($_GET['ok']) ? 'done' : 'form'));
        $proxyUrl = null;
        if ($state === 'done' && MeetingService::isDocx($m) && preg_match('/^[a-f0-9]{32}$/', $_GET['r'] ?? '')
            && DB::value('SELECT 1 FROM meeting_responses WHERE doc_token = ? AND meeting_id = ?', [$_GET['r'], $m['id']])) {
            $proxyUrl = "/gk/$token/proxy/{$_GET['r']}";
        }
        echo View::partial('Meetings::public', [
            'state'        => $state,
            'm'            => $m,
            'token'        => $token,
            'proxyUrl'     => $proxyUrl,
            'shareholders' => $state === 'form'
                ? DB::all(
                    'SELECT s.id, s.full_name, s.share_count IS NULL AS needs_shares, r.id IS NOT NULL AS answered
                     FROM meeting_shareholders s LEFT JOIN meeting_responses r ON r.shareholder_id = s.id
                     WHERE s.meeting_id = ? ORDER BY s.sort_order, s.id',
                    [$m['id']]
                ) : [],
        ]);
    }

    public function submit(string $token): void
    {
        $m = $this->load($token) ?? abort(404);
        if (!MeetingService::accepting($m)) {
            redirect("/gk/$token");
        }
        try {
            $doc = MeetingService::respond($m, $_POST, $_SERVER['REMOTE_ADDR'] ?? null);
        } catch (ValidationException $e) {
            fail($e, "/gk/$token");
        }
        redirect("/gk/$token?ok=1" . ($doc ? "&r=$doc" : ''));
    }

    /** PDF vekaletname örneği (yalnız form linkiyle). */
    public function doc(string $token): void
    {
        $m = $this->load($token) ?? abort(404);
        if (MeetingService::isDocx($m)) {
            abort(404);   // Word şablonu doldurulmadan paylaşılmaz
        }
        $path = MeetingService::docPath($m['proxy_doc_file']) ?? abort(404);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', (string) $m['proxy_doc_name']) . '"');
        readfile($path);
    }

    /** Doldurulmuş vekaletname: yanıta özel belirteçle, oturumsuz. */
    public function proxy(string $token, string $doc): void
    {
        $m = $this->load($token) ?? abort(404);
        $s = preg_match('/^[a-f0-9]{32}$/', $doc) ? DB::one(
            'SELECT s.*, r.attendance, r.proxy_name, r.proxy_tc, r.proxy_address
             FROM meeting_responses r JOIN meeting_shareholders s ON s.id = r.shareholder_id
             WHERE r.doc_token = ? AND r.meeting_id = ?',
            [$doc, $m['id']]
        ) : null;
        [$path, $name] = ($s ? MeetingService::filledProxy($m, $s) : null) ?? abort(404);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        unlink($path);
    }

    /** Dil: ?lang ile seçilen, yoksa müvekkilin yazışma dili. */
    private function load(string $token): ?array
    {
        $m = MeetingService::findByToken($token);
        $lang = $_GET['lang'] ?? null;
        if ($lang && in_array($lang, config('app.locales'), true)) {
            $_SESSION['gk_locale'] = $lang;
        }
        $client = $m ? DB::value('SELECT locale FROM clients WHERE id = ?', [$m['client_id']]) : null;
        Lang::set($_SESSION['gk_locale'] ?? $client ?? config('app.locale', 'tr'));
        return $m;
    }
}
