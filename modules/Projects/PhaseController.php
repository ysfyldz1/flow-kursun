<?php

declare(strict_types=1);

namespace Modules\Projects;

use App\Core\ValidationException;
use App\Core\View;
use App\Services\Access;
use App\Services\PhaseService;
use App\Services\ProjectService;

/** Kurşun: proje aşamalarını yönetme ekranı (/projects/{id}/phases). */
final class PhaseController
{
    public function index(string $id): void
    {
        $project = $this->project($id);
        $phases = PhaseService::forProject((int) $project['id']);
        $editId = (int) ($_GET['edit'] ?? 0);
        $editing = null;
        foreach ($phases as $ph) {
            if ((int) $ph['id'] === $editId) {
                $editing = $ph;
            }
        }
        View::render('Projects::phases', [
            'title'   => __('phases.title') . ' · ' . $project['name'],
            'project' => $project,
            'phases'  => $phases,
            'editing' => $editing,
        ]);
    }

    public function store(string $id): void
    {
        $project = $this->project($id);
        try {
            PhaseService::create((int) $project['id'], $_POST);
        } catch (ValidationException $e) {
            fail($e, "/projects/$id/phases#phase-form");
        }
        flash('success', __('app.saved'));
        redirect("/projects/$id/phases");
    }

    public function update(string $id, string $phase): void
    {
        $project = $this->project($id);
        try {
            PhaseService::update((int) $project['id'], (int) $phase, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/projects/$id/phases?edit=$phase#phase-form");
        }
        flash('success', __('app.saved'));
        redirect("/projects/$id/phases");
    }

    public function delete(string $id, string $phase): void
    {
        $this->act($id, fn ($pid) => PhaseService::delete($pid, (int) $phase));
    }

    public function move(string $id, string $phase): void
    {
        $this->act($id, fn ($pid) => PhaseService::move($pid, (int) $phase, ($_POST['dir'] ?? '') === 'up' ? -1 : 1), false);
    }

    public function approve(string $id, string $phase): void
    {
        $this->act($id, fn ($pid) => PhaseService::approve($pid, (int) $phase, !empty($_POST['approved'])));
    }

    public function complete(string $id, string $phase): void
    {
        $this->act($id, fn ($pid) => PhaseService::complete($pid, (int) $phase, !empty($_POST['done'])));
    }

    public function settings(string $id): void
    {
        $this->act($id, fn ($pid) => PhaseService::setWarnPct($pid, $_POST['phase_warn_pct'] ?? ''));
    }

    // --- İçe alma: dosya → düzenlenebilir önizleme → kaydet ---

    public function importUpload(string $id): void
    {
        $project = $this->project($id);
        try {
            $rows = \App\Services\PhaseImportService::parse($_FILES['file'] ?? []);
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
            redirect("/projects/$id/phases");
        }
        $_SESSION['_phase_import'][(int) $project['id']] = ['rows' => $rows, 'file' => (string) ($_FILES['file']['name'] ?? '')];
        redirect("/projects/$id/phases/import");
    }

    public function importPreview(string $id): void
    {
        $project = $this->project($id);
        $data = $_SESSION['_phase_import'][(int) $project['id']] ?? null;
        if (!$data) {
            redirect("/projects/$id/phases");
        }
        View::render('Projects::phases_import', [
            'title'   => __('phases.import_title'),
            'project' => $project,
            'rows'    => $data['rows'],
            'file'    => $data['file'],
        ]);
    }

    public function importSave(string $id): void
    {
        $project = $this->project($id);
        $pid = (int) $project['id'];
        $rows = array_values(array_filter((array) ($_POST['rows'] ?? []), fn ($r) => !empty($r['include'])));
        if (!$rows) {
            flash('error', __('phases.import_none_selected'));
            redirect("/projects/$id/phases/import");
        }
        try {
            \App\Core\DB::transaction(function () use ($pid, $rows) {
                foreach ($rows as $i => $r) {
                    try {
                        PhaseService::create($pid, $r);
                    } catch (ValidationException $e) {
                        // Hangi satırda hata olduğu görülsün
                        throw new ValidationException(['import' => __('phases.import_row_error', ['row' => $i + 1, 'error' => implode(' ', $e->errors)])]);
                    }
                }
            });
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
            redirect("/projects/$id/phases/import");
        }
        unset($_SESSION['_phase_import'][$pid]);
        flash('success', __('phases.import_saved', ['count' => count($rows)]));
        redirect("/projects/$id/phases");
    }

    public function importCancel(string $id): void
    {
        unset($_SESSION['_phase_import'][(int) $id]);
        redirect("/projects/$id/phases");
    }

    private function act(string $id, callable $fn, bool $toast = true): never
    {
        $project = $this->project($id);
        try {
            $fn((int) $project['id']);
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
            redirect("/projects/$id/phases");
        }
        if ($toast) {
            flash('success', __('app.saved'));
        }
        redirect("/projects/$id/phases");
    }

    private function project(string $id): array
    {
        return (Access::canSeeProject((int) $id) ? ProjectService::find((int) $id) : null) ?? abort(404);
    }
}
