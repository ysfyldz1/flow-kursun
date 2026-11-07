<?php

declare(strict_types=1);

namespace Modules\Projects;

use App\Core\Auth;
use App\Services\Access;
use App\Services\DocumentService;
use App\Services\Microsoft;
use App\Services\ProjectEmailService;
use App\Services\ProjectService;
use RuntimeException;

/** Proje sayfasındaki Yazışmalar: kişinin kendi posta kutusundan e-posta seçip projeye kaydetme. */
final class EmailController
{
    /** Seçici penceresi için arama (JSON). Bağlı değilse 401 ve profil adresi döner. */
    public function search(string $id): void
    {
        $project = $this->project($id);
        try {
            json_response(['messages' => ProjectEmailService::search((int) Auth::id(), (string) ($_GET['q'] ?? ''), $project)]);
        } catch (RuntimeException $e) {
            $this->fail($e);
        }
    }

    /** Seçilen e-postaları kaydeder; her biri ayrı denenir, biri hata verirse diğerleri yine kaydedilir. */
    public function store(string $id): void
    {
        $project = $this->project($id);
        $saved = $skipped = 0;
        $errors = [];
        foreach (array_slice((array) ($_POST['ids'] ?? []), 0, 20) as $messageId) {
            try {
                ProjectEmailService::save($project, (int) Auth::id(), (string) $messageId) ? $saved++ : $skipped++;
            } catch (RuntimeException $e) {
                if ($e->getCode() === 401) {
                    $this->fail($e);
                }
                $errors[] = $e->getMessage();
            }
        }
        json_response(['saved' => $saved, 'skipped' => $skipped, 'errors' => $errors], $saved || $skipped ? 200 : 502);
    }

    public function delete(string $id, string $email): void
    {
        $project = $this->project($id);
        try {
            ProjectEmailService::delete($project, (int) $email);
            flash('success', __('emails.removed'));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect("/projects/$id#emails");
    }

    private function project(string $id): array
    {
        $project = (Access::canSeeProject((int) $id) ? ProjectService::find((int) $id) : null) ?? abort(404);
        if (!DocumentService::enabled()) {
            abort(404);
        }
        return $project;
    }

    private function fail(RuntimeException $e): never
    {
        $status = $e->getCode() === 401 ? 401 : 502;
        json_response(['error' => $e->getMessage(), 'connect' => $status === 401 ? '/account' : null], $status);
    }
}
