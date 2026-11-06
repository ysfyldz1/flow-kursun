<?php

declare(strict_types=1);

namespace Modules\Expenses;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\ExpenseService;
use App\Services\ReportService;
use App\Services\TimeService;

final class ExpenseController
{
    public function __construct()
    {
        if (!can('expenses.own') && !can('expenses.all')) {
            abort(403);
        }
    }

    public function index(): void
    {
        $preset = in_array($_GET['period'] ?? '', ReportService::PERIODS, true) ? $_GET['period'] : 'this_month';
        [$from, $to] = ReportService::period($preset, $_GET['from'] ?? null, $_GET['to'] ?? null);
        $filters = [
            'period'     => $preset,
            'from'       => $from,
            'to'         => $to,
            'state'      => (string) ($_GET['state'] ?? ''),
            'user_id'    => (int) ($_GET['user_id'] ?? 0),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
        ];
        $rows = ExpenseService::list($filters);
        View::render('Expenses::index', [
            'title'    => __('expenses.title'),
            'rows'     => $rows,
            'filters'  => $filters,
            'totals'   => ReportService::totalsByCurrency($rows, ['amount']),
            'projects' => TimeService::projectOptions(Auth::id()),
            'users'    => can('expenses.all') ? DB::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name') : [],
            'entry'    => null,
        ]);
    }

    public function store(): void
    {
        try {
            ExpenseService::create($_POST, $_FILES['receipt'] ?? null);
        } catch (ValidationException $e) {
            fail($e, $this->back());
        }
        flash('success', __('expenses.saved'));
        redirect($this->back());
    }

    public function edit(string $id): void
    {
        $e = ExpenseService::find((int) $id) ?? abort(404);
        if (!ExpenseService::canModify($e)) {
            abort(403);
        }
        View::render('Expenses::edit', [
            'title'    => __('expenses.edit'),
            'entry'    => $e,
            'projects' => TimeService::projectOptions((int) $e['user_id']),
        ]);
    }

    public function update(string $id): void
    {
        try {
            ExpenseService::update((int) $id, $_POST, $_FILES['receipt'] ?? null);
        } catch (ValidationException $e) {
            fail($e, "/expenses/$id/edit");
        }
        flash('success', __('app.saved'));
        redirect("/expenses/$id/edit");
    }

    public function delete(string $id): void
    {
        try {
            ExpenseService::delete((int) $id);
            flash('success', __('app.deleted'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect('/expenses');
    }

    public function receipt(string $id): void
    {
        $e = ExpenseService::find((int) $id) ?? abort(404);
        if ((int) $e['user_id'] !== Auth::id() && !can('expenses.all')) {
            abort(403);
        }
        $path = ExpenseService::receiptPath($e) ?? abort(404);
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
    }

    private function back(): string
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $path = parse_url($ref, PHP_URL_PATH) ?: '/expenses';
        $query = parse_url($ref, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }
}
