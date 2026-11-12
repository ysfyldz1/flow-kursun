<?php

declare(strict_types=1);

namespace Modules\Billing;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\StatementPdf;
use App\Services\StatementService;

final class BillingController
{
    public function __construct()
    {
        if (!can('statements.manage') && !can('payments.manage')) {
            abort(403);
        }
    }

    public function index(): void
    {
        $filters = [
            'status'    => (string) ($_GET['status'] ?? 'open'),
            'q'         => (string) ($_GET['q'] ?? ''),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
        ];
        View::render('Billing::index', [
            'title'      => __('billing.title'),
            'statements' => StatementService::list($filters),
            'filters'    => $filters,
            'counts'     => StatementService::tabCounts(),
            'unbilled'   => can('statements.manage') ? StatementService::unbilledSummary() : [],
        ]);
    }

    public function create(): void
    {
        View::render('Billing::create', [
            'title'    => __('billing.new'),
            'clients'  => DB::all('SELECT id, code, name, currency FROM clients WHERE is_active = 1 ORDER BY name'),
            'unbilled' => StatementService::unbilledSummary(),
            // Kurşun: kapsam seçimi için projeler ve aşamaları
            'scopeProjects' => DB::all("SELECT id, client_id, currency, number, name FROM projects WHERE status <> 'closed' AND billing_type <> 'non_billable' ORDER BY number"),
            'scopePhases'   => DB::all('SELECT id, project_id, name, is_out_of_scope, is_optional, approved_at FROM project_phases ORDER BY project_id, is_out_of_scope, position, id'),
        ]);
    }

    public function store(): void
    {
        try {
            $id = StatementService::createDraft($_POST);
        } catch (ValidationException $e) {
            fail($e, '/billing/new');
        }
        flash('success', __('billing.draft_created'));
        redirect("/billing/$id");
    }

    public function show(string $id): void
    {
        $s = StatementService::find((int) $id) ?? abort(404);
        View::render('Billing::show', [
            'title'    => $s['number'] ?: __('billing.draft'),
            's'        => $s,
            'notices'  => \App\Services\PortalService::notices((int) $id),
            'projects' => DB::all('SELECT id, number, name FROM projects WHERE client_id = ? AND currency = ? ORDER BY number', [$s['client_id'], $s['currency']]),
            'phaseGroups'   => StatementService::phaseGroups((int) $id),
            'projectPhases' => StatementService::projectPhases((int) $id),
        ]);
    }

    public function update(string $id): void
    {
        $this->run(fn () => StatementService::updateDraft((int) $id, $_POST), $id, __('app.saved'));
    }

    public function addLine(string $id): void
    {
        $this->run(fn () => StatementService::addLine((int) $id, $_POST), $id, __('app.saved'));
    }

    public function removeLine(string $id, string $line): void
    {
        $this->run(fn () => StatementService::removeLine((int) $id, (int) $line), $id, __('app.deleted'));
    }

    // Kurşun: aşama bedeli / ek zaman bedeli düğmeleri ana formun içinde; önce formdaki düzenlemeler kaydedilir
    public function phaseFee(string $id, string $phase): void
    {
        $this->run(function () use ($id, $phase) {
            StatementService::updateDraft((int) $id, $_POST);
            StatementService::addPhaseFeeLine((int) $id, (int) $phase);
        }, $id, __('app.saved'));
    }

    public function phaseExtra(string $id, string $phase): void
    {
        $this->run(function () use ($id, $phase) {
            StatementService::updateDraft((int) $id, $_POST);
            StatementService::addPhaseExtra((int) $id, (int) $phase);
        }, $id, __('app.saved'));
    }

    public function split(string $id, string $line): void
    {
        $this->run(fn () => StatementService::splitLine((int) $id, (int) $line, (array) ($_POST['parts'] ?? [])), $id, __('billing.split_done'));
    }

    public function issue(string $id): void
    {
        $this->run(fn () => StatementService::issue((int) $id), $id, __('billing.issued'));
    }

    public function cancel(string $id): void
    {
        $s = StatementService::find((int) $id) ?? abort(404);
        try {
            StatementService::cancel((int) $id);
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
            redirect("/billing/$id");
        }
        flash('success', __($s['status'] === 'draft' ? 'billing.draft_deleted' : 'billing.cancelled'));
        redirect($s['status'] === 'draft' ? '/billing?status=draft' : "/billing/$id");
    }

    public function pdf(string $id): void
    {
        $s = StatementService::find((int) $id) ?? abort(404);
        $pdf = StatementPdf::render($s);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . StatementPdf::filename($s) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    public function uploadInvoice(string $id): void
    {
        $this->run(fn () => StatementService::attachInvoice((int) $id, $_FILES['invoice'] ?? [], (string) ($_POST['invoice_number'] ?? '')), $id, __('billing.invoice_uploaded'));
    }

    public function downloadInvoice(string $id): void
    {
        $s = StatementService::find((int) $id) ?? abort(404);
        $path = StatementService::invoicePath($s) ?? abort(404);
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . ($s['invoice_number'] ?: $s['number']) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    public function addPayment(string $id): void
    {
        $this->run(fn () => StatementService::addPayment((int) $id, $_POST), $id, __('billing.payment_recorded'));
    }

    public function deletePayment(string $id, string $payment): void
    {
        $this->run(fn () => StatementService::deletePayment((int) $id, (int) $payment), $id, __('app.deleted'));
    }

    private function run(callable $action, string $id, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            fail($e, "/billing/$id");
        }
        flash('success', $message);
        redirect("/billing/$id");
    }
}
