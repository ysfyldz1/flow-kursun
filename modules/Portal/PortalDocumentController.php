<?php

declare(strict_types=1);

namespace Modules\Portal;

use App\Core\DB;
use App\Core\PortalAuth;
use App\Core\ValidationException;
use App\Services\DocumentService;
use App\Services\Mailer;
use RuntimeException;

/**
 * Müvekkilin proje belgeleri: yalnızca kendisiyle paylaşılanları görür/indirir, projeye belge yükleyebilir.
 * Klasörün tamamını asla görmez.
 */
final class PortalDocumentController
{
    /** Yükleme oturumu başlatıldıktan sonra tamamlanma bildirimi için tanınan süre. */
    private const UPLOAD_WINDOW = 3600;

    public function download(string $id, string $share): void
    {
        $project = $this->project($id);
        $s = DB::one('SELECT * FROM document_shares WHERE id = ? AND project_id = ?', [(int) $share, $project['id']]) ?? abort(404);
        try {
            $item = DocumentService::item(DocumentService::projectFolder($project), $s['item_id']);
        } catch (RuntimeException) {
            abort(404);   // ofiste silinmiş ya da taşınmış
        }
        header('Location: ' . $item['@microsoft.graph.downloadUrl']);
    }

    public function upload(string $id): void
    {
        $project = $this->project($id);
        try {
            $url = DocumentService::uploadSession(DocumentService::projectFolder($project), (string) ($_POST['name'] ?? ''), (int) ($_POST['size'] ?? 0));
        } catch (ValidationException $e) {
            json_response(['error' => reset($e->errors)], 422);
        } catch (RuntimeException) {
            json_response(['error' => __('docs.upload_failed')], 502);
        }
        // Tamamlanma bildirimi yalnızca bu oturumda başlatılmış bir yükleme için kabul edilir
        $_SESSION['_portal_uploads'][$project['id']][] = time();
        json_response(['uploadUrl' => $url]);
    }

    /**
     * Tarayıcı yüklemeyi bitirince öğe kimliğini bildirir. Ofisin dosyasının kimliği uydurulup
     * "ben yükledim" denerek paylaşıma alınamasın: oturumda bekleyen yükleme olmalı ve öğe yeni oluşturulmuş olmalı.
     */
    public function complete(string $id): void
    {
        $project = $this->project($id);
        $pending = array_filter($_SESSION['_portal_uploads'][$project['id']] ?? [], fn ($t) => $t > time() - self::UPLOAD_WINDOW);
        if (!$pending) {
            json_response(['error' => 'No pending upload'], 409);
        }
        try {
            $item = DocumentService::item(DocumentService::projectFolder($project), (string) ($_POST['item_id'] ?? ''));
        } catch (RuntimeException) {
            json_response(['error' => 'Not found'], 404);
        }
        $created = strtotime((string) ($item['createdDateTime'] ?? $item['lastModifiedDateTime']));
        if ($created < time() - self::UPLOAD_WINDOW || DB::value('SELECT 1 FROM document_shares WHERE project_id = ? AND item_id = ?', [$project['id'], $item['id']])) {
            json_response(['error' => 'Not a new upload'], 409);
        }
        array_shift($pending);
        $_SESSION['_portal_uploads'][$project['id']] = array_values($pending);

        DocumentService::recordClientUpload($project, $item, (int) PortalAuth::id());
        $this->notify($project, $item['name']);
        json_response(['ok' => true]);
    }

    /** Proje sorumlusuna haber verilir; sorumlu yoksa kimseye gitmez (proje sayfasında rozetle görünür). */
    private function notify(array $project, string $fileName): void
    {
        $to = DB::one('SELECT name, email, locale FROM users WHERE id = ? AND is_active = 1', [$project['responsible_user_id']]);
        if (!$to || !Mailer::delivers()) {
            return;
        }
        Mailer::sendView($to['email'], $to['locale'], 'client_upload', [
            'name'    => $to['name'],
            'client'  => (string) DB::value('SELECT name FROM clients WHERE id = ?', [$project['client_id']]),
            'project' => $project['number'] . ' ' . $project['name'],
            'file'    => $fileName,
            'uploader'=> (string) (PortalAuth::user()['name'] ?? ''),
            'url'     => rtrim((string) config('app.url'), '/') . '/projects/' . $project['id'] . '#documents',
        ]);
    }

    private function project(string $id): array
    {
        if (!DocumentService::enabled()) {
            abort(404);
        }
        return DB::one(
            'SELECT * FROM projects WHERE id = ? AND client_id = ? AND portal_visible = 1',
            [(int) $id, PortalAuth::clientId()]
        ) ?? abort(404);
    }
}
