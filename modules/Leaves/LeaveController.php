<?php

declare(strict_types=1);

namespace Modules\Leaves;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\LeaveService;
use App\Services\Settings;

final class LeaveController
{
    public function __construct()
    {
        if (!can('leaves.own') && !can('leaves.approve') && !can('leaves.manage')) {
            abort(403);
        }
    }

    public function index(): void
    {
        $toApprove = LeaveService::list('approve');
        $default = $toApprove ? 'approve' : 'mine';
        $tab = in_array($_GET['tab'] ?? '', ['mine', 'approve', 'all'], true) ? $_GET['tab'] : $default;
        if ($tab === 'all' && !can('leaves.manage')) {
            $tab = 'mine';
        }
        $year = (int) ($_GET['year'] ?? date('Y'));

        View::render('Leaves::index', [
            'title'     => __('leaves.title'),
            'tab'       => $tab,
            'year'      => $year,
            'rows'      => $tab === 'approve' ? $toApprove : LeaveService::list($tab, $year),
            'pending'   => count($toApprove),
            'balance'   => LeaveService::balance(Auth::id()),
            'approvers' => LeaveService::approversFor(Auth::id()),
            'users'     => can('leaves.manage') ? DB::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name') : [],
        ]);
    }

    public function store(): void
    {
        try {
            $id = LeaveService::create($_POST, $_FILES['report'] ?? null);
        } catch (ValidationException $e) {
            fail($e, '/leaves');
        }
        flash('success', __('leaves.requested'));
        redirect("/leaves/$id");
    }

    /** Formda canlı gün sayısı. */
    public function days(): void
    {
        $start = (string) ($_GET['start'] ?? '');
        $end = (string) ($_GET['end'] ?? '') ?: $start;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) {
            json_response(['days' => null]);
        }
        json_response(['days' => LeaveService::countDays($start, $end, !empty($_GET['half_start']), !empty($_GET['half_end']) && $end !== $start)]);
    }

    public function show(string $id): void
    {
        $leave = $this->load($id);
        View::render('Leaves::show', [
            'title'     => __('leaves.request'),
            'l'         => $leave,
            'own'       => (int) $leave['user_id'] === Auth::id(),
            'canReview' => LeaveService::canReview($leave),
            'balance'   => LeaveService::balance((int) $leave['user_id']),
            'approvers' => LeaveService::approversFor((int) $leave['user_id']),
        ]);
    }

    public function review(string $id): void
    {
        $this->load($id);
        try {
            LeaveService::review((int) $id, (string) ($_POST['decision'] ?? ''), (string) ($_POST['review_note'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, "/leaves/$id");
        }
        flash('success', __($_POST['decision'] === 'approved' ? 'leaves.approved_msg' : 'leaves.rejected_msg'));
        redirect("/leaves/$id");
    }

    public function cancel(string $id): void
    {
        $this->load($id);
        try {
            LeaveService::cancel((int) $id, (string) ($_POST['review_note'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, "/leaves/$id");
        }
        flash('success', __('leaves.cancelled_msg'));
        redirect("/leaves/$id");
    }

    public function signed(string $id): void
    {
        $this->load($id);
        try {
            LeaveService::uploadSigned((int) $id, $_FILES['signed'] ?? null);
        } catch (ValidationException $e) {
            fail($e, "/leaves/$id");
        }
        flash('success', __('leaves.signed_saved'));
        redirect("/leaves/$id");
    }

    /** Islak imza için yazdırılabilir form. */
    public function print(string $id): void
    {
        $leave = $this->load($id);
        if ($leave['status'] !== 'approved') {
            abort(404);
        }
        echo View::partial('Leaves::print', [
            'l'       => $leave,
            'balance' => LeaveService::balance((int) $leave['user_id']),
            'org'     => (string) Settings::get('org.name', config('app.name')),
            'logo'    => (bool) Settings::get('org.logo'),
        ]);
    }

    public function file(string $id, string $kind): void
    {
        $leave = $this->load($id);
        $path = LeaveService::filePath($leave[$kind === 'signed' ? 'signed_file' : 'report_file']) ?? abort(404);
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
    }

    public function balances(): void
    {
        View::render('Leaves::balances', [
            'title' => __('leaves.balances'),
            'rows'  => LeaveService::balances(),
        ]);
    }

    private function load(string $id): array
    {
        $leave = LeaveService::find((int) $id) ?? abort(404);
        if (!LeaveService::canView($leave)) {
            abort(404);
        }
        return $leave;
    }
}
