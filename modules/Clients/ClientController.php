<?php

declare(strict_types=1);

namespace Modules\Clients;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\ClientService;

final class ClientController
{
    public function index(): void
    {
        $filters = [
            'q'      => (string) ($_GET['q'] ?? ''),
            'status' => (string) ($_GET['status'] ?? 'active'),
            'type'   => (string) ($_GET['type'] ?? ''),
        ];
        View::render('Clients::index', [
            'title'   => __('clients.title'),
            'clients' => ClientService::list($filters),
            'filters' => $filters,
        ]);
    }

    public function show(string $id): void
    {
        $client = ClientService::find((int) $id) ?? abort(404);
        View::render('Clients::show', [
            'title'    => $client['name'],
            'client'   => $client,
            'projects' => can('projects.view') ? \App\Services\ProjectService::forClient((int) $id) : [],
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $this->form(ClientService::find((int) $id) ?? abort(404));
    }

    public function store(): void
    {
        try {
            $id = ClientService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, '/clients/new');
        }
        flash('success', __('app.saved'));
        redirect("/clients/$id");
    }

    public function update(string $id): void
    {
        ClientService::find((int) $id) ?? abort(404);
        try {
            ClientService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/clients/$id/edit");
        }
        flash('success', __('app.saved'));
        redirect("/clients/$id");
    }

    public function toggle(string $id): void
    {
        $client = ClientService::find((int) $id) ?? abort(404);
        ClientService::setActive((int) $id, !$client['is_active']);
        flash('success', __('app.saved'));
        redirect("/clients/$id");
    }

    private function form(?array $client): void
    {
        View::render('Clients::form', [
            'title'  => $client ? $client['name'] : __('clients.new'),
            'client' => $client,
            'users'  => DB::all('SELECT id, name, title, hourly_rate, rate_currency FROM users WHERE is_active = 1 ORDER BY name'),
        ]);
    }
}
