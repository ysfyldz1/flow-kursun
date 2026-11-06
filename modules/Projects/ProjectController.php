<?php

declare(strict_types=1);

namespace Modules\Projects;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\ProjectService;
use App\Services\RateService;

final class ProjectController
{
    public function index(): void
    {
        $filters = [
            'q'         => (string) ($_GET['q'] ?? ''),
            'status'    => (string) ($_GET['status'] ?? 'open'),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
            'mine'      => !empty($_GET['mine']),
        ];
        View::render('Projects::index', [
            'title'    => __('projects.title'),
            'projects' => ProjectService::list($filters),
            'filters'  => $filters,
        ]);
    }

    public function show(string $id): void
    {
        $project = ProjectService::find((int) $id) ?? abort(404);

        // Her ekip üyesi için geçerli ücret ve kaynağı (yalnızca yetkiliye gösterilir)
        $rates = [];
        if (can('rates.view')) {
            foreach ($project['members'] as $m) {
                $rates[$m['user_id']] = RateService::resolve((int) $project['id'], (int) $m['user_id']);
            }
        }
        View::render('Projects::show', [
            'title'   => $project['name'],
            'project' => $project,
            'rates'   => $rates,
            'entries' => \App\Services\TimeService::forProject((int) $project['id']),
            'totals'  => \App\Services\TimeService::projectTotals((int) $project['id']),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $this->form(ProjectService::find((int) $id) ?? abort(404));
    }

    public function store(): void
    {
        try {
            $id = ProjectService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, '/projects/new' . (!empty($_POST['client_id']) ? '?client_id=' . (int) $_POST['client_id'] : ''));
        }
        flash('success', __('app.saved'));
        redirect("/projects/$id/edit");
    }

    public function update(string $id): void
    {
        ProjectService::find((int) $id) ?? abort(404);
        try {
            ProjectService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/projects/$id/edit");
        }
        flash('success', __('app.saved'));
        redirect("/projects/$id/edit");
    }

    private function form(?array $project): void
    {
        View::render('Projects::form', [
            'title'    => $project ? $project['name'] : __('projects.new'),
            'project'  => $project,
            'clients'  => DB::all('SELECT id, code, name, currency FROM clients WHERE is_active = 1 ORDER BY name'),
            'users'    => DB::all('SELECT id, name, title FROM users WHERE is_active = 1 ORDER BY name'),
            'clientId' => (int) ($_GET['client_id'] ?? 0),
        ]);
    }
}
