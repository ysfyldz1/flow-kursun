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

    /** Kurşun: yeni döküm — müvekkil seç, açık projeleri kart olarak gör. */
    public function create(): void
    {
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $client = $clientId ? DB::one('SELECT id, code, name, currency FROM clients WHERE id = ?', [$clientId]) : null;
        View::render('Billing::start', [
            'title'    => __('billing.new'),
            'clients'  => DB::all(
                "SELECT c.id, c.code, c.name, c.currency,
                        (SELECT COUNT(*) FROM time_entries t WHERE t.client_id = c.id AND t.statement_id IS NULL AND t.is_billable = 1) AS unbilled
                 FROM clients c WHERE c.is_active = 1 ORDER BY c.name"
            ),
            'client'   => $client,
            'projects' => $client ? \App\Services\BillingOverviewService::clientProjects((int) $client['id']) : [],
            // Faturalanmamış hizmeti olan müvekkiller (tutara göre çoktan aza)
            'waiting'  => $client ? [] : (function () {
                $rows = StatementService::unbilledSummary();
                usort($rows, fn ($a, $b) => [(float) $b['amount'], (int) $b['minutes']] <=> [(float) $a['amount'], (int) $a['minutes']]);
                return $rows;
            })(),
        ]);
    }

    /** Kurşun: proje faturalama — aşama özeti ve aşama sekmeleri. */
    public function project(string $id): void
    {
        $project = DB::one(
            'SELECT p.*, c.name AS client_name, c.code AS client_code FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [(int) $id]
        ) ?? abort(404);
        $groups = \App\Services\BillingOverviewService::project((int) $project['id']);
        $tab = (string) ($_GET['tab'] ?? '');
        $keys = array_column($groups, 'key');
        if (!in_array($tab, $keys, true)) {
            // Varsayılan: faturalanmamış kaydı olan ilk sekme
            $open = array_values(array_filter($groups, fn ($g) => $g['state'] === 'open'));
            $tab = ($open[0]['key'] ?? null) ?? ($keys[0] ?? 'none');
        }
        $current = null;
        foreach ($groups as $g) {
            if ($g['key'] === $tab) {
                $current = $g;
            }
        }
        // Hızlı tarih filtresi: all | this | last | custom (from/to)
        $range = in_array($_GET['range'] ?? '', ['this', 'last', 'custom'], true) ? $_GET['range'] : 'all';
        $valid = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        [$from, $to] = match ($range) {
            'this'   => [date('Y-m-01'), date('Y-m-t')],
            'last'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            'custom' => [$valid($_GET['from'] ?? null), $valid($_GET['to'] ?? null)],
            default  => [null, null],
        };
        View::render('Billing::project', [
            'title'   => $project['name'],
            'project' => $project,
            'groups'  => $groups,
            'tab'     => $tab,
            'current' => $current,
            'range'   => $range,
            'from'    => $from,
            'to'      => $to,
            'entries' => $current ? \App\Services\BillingOverviewService::unbilledEntries((int) $project['id'], $current['phase_id'], $current['key'] === 'none', $from, $to) : [],
            'phased'  => (bool) array_filter($groups, fn ($g) => $g['phase_id'] !== null),
        ]);
    }

    // Kurşun: proje faturalama ekranında faturalanmamış kaydı aşamaya taşı / böl
    public function moveEntry(string $id, string $entry): void
    {
        $this->entryAction($id, fn () => \App\Services\PhaseService::moveEntry((int) $id, (int) $entry, (int) ($_POST['phase_id'] ?? 0) ?: null), __('billing.entry_moved'));
    }

    public function splitEntry(string $id, string $entry): void
    {
        $this->entryAction($id, fn () => \App\Services\PhaseService::splitEntry((int) $id, (int) $entry, (array) ($_POST['parts'] ?? [])), __('billing.split_done'));
    }

    private function entryAction(string $id, callable $fn, string $message): never
    {
        $tab = preg_match('/^[\w-]+$/', (string) ($_POST['tab'] ?? '')) ? (string) $_POST['tab'] : '';
        $to = "/billing/new/project/$id" . ($tab !== '' ? "?tab=$tab" : '');
        try {
            $fn();
            flash('success', $message);
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect($to);
    }

    /** Eski ayrıntılı form (tarih aralığı, müvekkil geneli) — gerekirse. */
    public function createAdvanced(): void
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
            // Proje ekranından gelindiyse oraya, hata iletisiyle dön
            if (preg_match('#^/billing/new(/project/\d+(\?tab=[\w-]+)?|\?client_id=\d+)?$#', (string) ($_POST['back'] ?? ''))) {
                flash('error', implode(' ', $e->errors));
                redirect((string) $_POST['back']);
            }
            fail($e, '/billing/new/advanced');
        }
        flash('success', __(StatementService::$reusedDraft ? 'billing.draft_reused' : 'billing.draft_created'));
        redirect("/billing/$id");
    }

    /**
     * Kurşun: kaydetmeden önizleme. Taslak bir işlem içinde oluşturulup ekran çizilir, sonra işlem geri alınır;
     * kayıtlar kilitlenmez. "Taslak olarak kaydet" aynı alanlarla POST /billing yapar.
     */
    public function preview(): void
    {
        $fields = array_intersect_key($_GET, array_flip(['client_id', 'currency', 'project_id', 'phase_id', 'unphased', 'back', 'entry_ids', 'period_start', 'period_end']));
        if (isset($fields['entry_ids'])) {
            $fields['entry_ids'] = array_values(array_filter(array_map('intval', (array) $fields['entry_ids'])));
        }
        $back = preg_match('#^/billing/new(/project/\d+(\?tab=[\w-]+)?|\?client_id=\d+)?$#', (string) ($fields['back'] ?? '')) ? $fields['back'] : '/billing/new';
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $id = StatementService::createDraft($fields);
            if (StatementService::$reusedDraft) {
                $pdo->rollBack();
                flash('success', __('billing.draft_reused'));
                redirect("/billing/$id");
            }
            $s = StatementService::find($id);
            $data = [
                'title'         => __('billing.preview_title'),
                's'             => $s,
                'notices'       => [],
                'projects'      => [],
                'phaseGroups'   => StatementService::phaseGroups($id),
                'projectPhases' => [],
                'preview'       => ['fields' => $fields, 'back' => $back],
            ];
        } catch (ValidationException $e) {
            $pdo->rollBack();
            flash('error', implode(' ', $e->errors));
            redirect($back);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $pdo->rollBack();
        View::render('Billing::show', $data);
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
            'pending'       => $s['status'] === 'draft' ? StatementService::pendingForDraft((int) $id) : [],
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

    /** Kapsama sonradan giren kayıtları taslağa ekle (taslak ya da proje ekranından). */
    public function addPending(string $id): void
    {
        try {
            $n = StatementService::addPendingToDraft((int) $id);
            flash('success', __('billing.pending_added', ['count' => $n]));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        $back = (string) ($_POST['back'] ?? '');
        redirect(preg_match('#^/billing/new/project/\d+(\?tab=[\w-]+)?$#', $back) ? $back : "/billing/$id");
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
