<?php

declare(strict_types=1);

namespace Modules\Projects;

use App\Core\Auth;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\DocumentService;
use App\Services\ProjectService;
use RuntimeException;

/** Proje sayfasındaki Belgeler bölümü. Liste ayrı istekle yüklenir; proje sayfası Graph'ı beklemesin. */
final class DocumentController
{
    public function index(string $id): void
    {
        $project = $this->project($id);
        try {
            [$folder, $crumbs] = DocumentService::folder($project, $_GET['folder'] ?? null);
            $items = DocumentService::list($folder);
        } catch (RuntimeException $e) {
            http_response_code(502);
            echo '<div class="empty text-warn">' . e(__('docs.load_failed', ['error' => $e->getMessage()])) . '</div>';
            return;
        }
        echo View::partial('Projects::_documents', [
            'project' => $project,
            'items'   => $items,
            'folder'  => $folder,
            'crumbs'  => $crumbs,
            'shares'  => DocumentService::shares((int) $project['id']),
        ]);
    }

    public function upload(string $id): void
    {
        $project = $this->project($id);
        try {
            $url = DocumentService::uploadSession(DocumentService::folder($project, $_GET['folder'] ?? null)[0], (string) ($_POST['name'] ?? ''), (int) ($_POST['size'] ?? 0));
        } catch (ValidationException $e) {
            json_response(['error' => reset($e->errors)], 422);
        } catch (RuntimeException $e) {
            json_response(['error' => $e->getMessage()], 502);
        }
        json_response(['uploadUrl' => $url]);
    }

    /** Açık klasörün içinde yeni klasör. */
    public function createFolder(string $id): void
    {
        $project = $this->project($id);
        try {
            [$parent] = DocumentService::folder($project, $_POST['folder'] ?? null);
            $f = DocumentService::createFolder($parent, (string) ($_POST['name'] ?? ''));
        } catch (ValidationException $e) {
            json_response(['error' => reset($e->errors)], 422);
        } catch (RuntimeException $e) {
            json_response(['error' => $e->getMessage()], $e->getCode() === 404 ? 404 : 502);
        }
        json_response(['id' => $f['id']]);
    }

    public function download(string $id, string $item): void
    {
        $i = $this->item($id, $item);
        header('Location: ' . $i['@microsoft.graph.downloadUrl']);
    }

    public function open(string $id, string $item): void
    {
        header('Location: ' . DocumentService::openUrl($this->item($id, $item)));
    }

    public function share(string $id, string $item): void
    {
        $project = $this->project($id);
        $i = $this->item($id, $item);
        $shared = DocumentService::toggleShare($project, $i, (int) Auth::id());
        json_response(['shared' => $shared]);
    }

    private function project(string $id): array
    {
        $project = (\App\Services\Access::canSeeProject((int) $id) ? ProjectService::find((int) $id) : null) ?? abort(404);
        if (!DocumentService::enabled()) {
            abort(404);
        }
        return $project;
    }

    private function item(string $id, string $item): array
    {
        $project = $this->project($id);
        try {
            return DocumentService::item(DocumentService::projectFolder($project), $item);
        } catch (RuntimeException) {
            abort(404);
        }
    }
}
