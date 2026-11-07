<?php

declare(strict_types=1);

namespace Modules\Tasks;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\TaskService;

final class TaskController
{
    public function __construct()
    {
        if (!can('tasks.own') && !can('tasks.all')) {
            abort(403);
        }
    }

    public function index(): void
    {
        $scopes = ['mine', 'given'] + (can('tasks.all') ? [2 => 'all'] : []);
        $scope = in_array($_GET['scope'] ?? '', $scopes, true) ? $_GET['scope'] : 'mine';
        $state = ($_GET['state'] ?? '') === 'done' ? 'done' : 'open';
        View::render('Tasks::index', [
            'title'  => __('tasks.title'),
            'scopes' => $scopes,
            'scope'  => $scope,
            'state'  => $state,
            'rows'   => TaskService::list($scope, $state),
        ] + $this->options());
    }

    public function store(): void
    {
        try {
            $id = TaskService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, $this->back());
        }
        $this->flashSaved($id, 'tasks.created');
        redirect($this->back());
    }

    /** Görevi veren düzenleme formunu, atanan salt okunur ayrıntıyı görür. */
    public function show(string $id): void
    {
        $t = TaskService::find((int) $id) ?? abort(404);
        if (!TaskService::canComplete($t)) {
            abort(404);
        }
        View::render('Tasks::show', [
            'title' => $t['title'],
            't'     => $t,
            'edit'  => TaskService::canEdit($t),
        ] + $this->options());
    }

    public function update(string $id): void
    {
        try {
            TaskService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/tasks/$id");
        }
        $this->flashSaved((int) $id, 'app.saved');
        redirect("/tasks/$id");
    }

    public function done(string $id): void
    {
        try {
            flash('success', __(TaskService::toggleDone((int) $id) ? 'tasks.done_msg' : 'tasks.reopened_msg'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        back();
    }

    public function delete(string $id): void
    {
        $t = TaskService::find((int) $id);
        try {
            TaskService::delete((int) $id);
            flash('success', __('app.deleted'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect($t && $t['project_id'] ? '/projects/' . $t['project_id'] : '/tasks');
    }

    /** İzinli atanan varsa kayıt mesajına not düşülür. */
    private function flashSaved(int $id, string $key): void
    {
        $away = TaskService::assigneesOnLeave(TaskService::find($id));
        flash('success', __($key) . ($away ? ' ' . __('tasks.on_leave_note', ['names' => implode(', ', $away)]) : ''));
    }

    private function options(): array
    {
        return [
            'projects' => DB::all(
                "SELECT p.id, p.number, p.name, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id
                 WHERE p.status <> 'closed' AND " . \App\Services\Access::projectWhere()[0] . " ORDER BY p.number",
                \App\Services\Access::projectWhere()[1]
            ),
            'users'    => DB::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'),
            'me'       => Auth::id(),
        ];
    }

    private function back(): string
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $path = parse_url($ref, PHP_URL_PATH) ?: '/tasks';
        $query = parse_url($ref, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }
}
