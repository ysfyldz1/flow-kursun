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
