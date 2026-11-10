<?php

declare(strict_types=1);

namespace Modules\Advances;

use App\Core\PortalAuth;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Access;
use App\Services\ClientService;
use App\Services\ReportService;

final class AdvanceController
{
    public function index(): void
    {
        View::render('Advances::index', [
            'title' => __('adv.title'),
            'rows'  => AdvanceService::overview(),
        ]);
    }

    public function show(string $id): void
    {
        $client = $this->client($id);
        View::render('Advances::show', [
            'title'  => __('adv.title') . ' · ' . $client['name'],
            'client' => $client,
        ] + $this->statement((int) $id));
    }

    public function pdf(string $id): void
    {
        $this->sendPdf($this->client($id));
    }

    public function store(string $id): void
    {
        $this->client($id);
        try {
            AdvanceService::create((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/clients/$id/advance");
        }
        flash('success', __('app.saved'));
        redirect("/clients/$id/advance?currency=" . urlencode((string) $_POST['currency']));
    }

    public function toggle(string $id): void
    {
        $client = $this->client($id);
        AdvanceService::setEnabled((int) $id, !$client['uses_advance']);
        flash('success', __('app.saved'));
        redirect("/clients/$id/advance");
    }

    public function delete(string $id, string $entry): void
    {
        $this->client($id);
        try {
            AdvanceService::delete((int) $id, (int) $entry);
            flash('success', __('app.deleted'));
        } catch (ValidationException $e) {
            flash('error', array_values($e->errors)[0]);
        }
        redirect("/clients/$id/advance");
    }

    public function portal(): void
    {
        View::render('Advances::portal', ['title' => __('adv.title')] + $this->statement(PortalAuth::clientId()), 'portal');
    }

    public function portalPdf(): void
    {
        $this->sendPdf(ClientService::find(PortalAuth::clientId()) ?? abort(404));
    }

    /** Ekran ve PDF için ortak: para birimi, dönem ve ekstre. */
    private function statement(int $clientId): array
    {
        $currencies = AdvanceService::currencies($clientId);
        $currency = in_array($_GET['currency'] ?? '', $currencies, true) ? $_GET['currency'] : $currencies[0];
        $preset = in_array($_GET['period'] ?? '', ReportService::PERIODS, true) ? $_GET['period'] : 'this_month';
        [$from, $to] = ReportService::period($preset, $_GET['from'] ?? null, $_GET['to'] ?? null);
        return [
            'balances'   => AdvanceService::balances($clientId),
            'currencies' => $currencies,
            'currency'   => $currency,
            'filters'    => ['period' => $preset, 'from' => $from, 'to' => $to],
            'ledger'     => AdvanceService::ledger($clientId, $currency, $from, $to),
        ];
    }

    private function sendPdf(array $client): void
    {
        $d = $this->statement((int) $client['id']);
        $pdf = AdvancePdf::render($client, $d['currency'], $d['ledger'], $d['filters']['from'], $d['filters']['to']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="'
            . AdvancePdf::filename($client, $d['currency'], $d['filters']['from'], $d['filters']['to']) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    private function client(string $id): array
    {
        return (Access::canSeeClient((int) $id) ? ClientService::find((int) $id) : null) ?? abort(404);
    }
}
