<?php

declare(strict_types=1);

namespace Modules\Files;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Access;
use App\Services\DocumentService;
use App\Services\Microsoft;
use App\Services\OneDriveService;
use App\Services\ProjectService;
use RuntimeException;

final class FileController
{
    public function __construct()
    {
        if (!DocumentService::enabled()) {
            abort(404);
        }
    }

    public function index(): void
    {
        [$where, $params] = Access::projectWhere();
        View::render('Files::index', [
            'title'     => __('onedrive.title'),
            'connected' => (bool) Microsoft::connection((int) Auth::id()),
            'projects'  => DB::all(
                "SELECT p.id, p.number, p.name, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id
                 WHERE p.status <> 'closed' AND $where ORDER BY p.number",
                $params
            ),
        ]);
    }

    /** Gezgin için klasör içeriği ya da arama (JSON). Bağlı değilse 401 + profil adresi. */
    public function browse(): void
    {
        try {
            json_response(OneDriveService::browse((int) Auth::id(), $_GET['folder'] ?? null, (string) ($_GET['q'] ?? '')));
        } catch (RuntimeException $e) {
            self::fail($e);
        }
    }

    /** Seçilen dosyaları bir projenin belge klasörüne kopyalar. */
    public function send(): void
    {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $project = (Access::canSeeProject($projectId) ? ProjectService::find($projectId) : null);
        if (!$project) {
            json_response(['error' => __('onedrive.choose_project')], 422);
        }
        self::copy($project, null);
    }

    /** Proje Belgeler kutusundaki "OneDrive'dan ekle" de aynı işi açık klasöre yapar. */
    public static function copy(array $project, ?string $folder): never
    {
        try {
            [$target] = DocumentService::folder($project, $folder);
            [$done, $errors] = OneDriveService::copyMany((int) Auth::id(), (array) ($_POST['ids'] ?? []), $target, (int) $project['id']);
        } catch (RuntimeException $e) {
            self::fail($e);
        }
        json_response([
            'copied'  => $done,
            'errors'  => $errors,
            'project' => '/projects/' . $project['id'] . '#documents',
            'message' => __('onedrive.copied', ['count' => $done, 'project' => $project['number']]),
        ], $done ? 200 : 502);
    }

    private static function fail(RuntimeException $e): never
    {
        $status = in_array($e->getCode(), [401, 404, 422], true) ? $e->getCode() : 502;
        json_response(['error' => $e->getMessage(), 'connect' => $status === 401 ? '/account' : null], $status);
    }
}
