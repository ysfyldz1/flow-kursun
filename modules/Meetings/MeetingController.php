<?php

declare(strict_types=1);

namespace Modules\Meetings;

use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\MeetingService;
use App\Services\Spreadsheet;

final class MeetingController
{
    public function index(): void
    {
        $when = ($_GET['when'] ?? '') === 'past' ? 'past' : 'upcoming';
        View::render('Meetings::index', [
            'title' => __('meetings.title'),
            'when'  => $when,
            'rows'  => MeetingService::list($when),
        ]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $this->form($this->load($id));
    }

    public function store(): void
    {
        try {
            $id = MeetingService::save(null, $_POST, $_FILES['proxy_doc'] ?? null);
        } catch (ValidationException $e) {
            fail($e, '/meetings/new' . (!empty($_POST['client_id']) ? '?client_id=' . (int) $_POST['client_id'] : ''));
        }
        flash('success', __('meetings.created'));
        redirect("/meetings/$id");
    }

    public function update(string $id): void
    {
        $this->load($id);
        try {
            MeetingService::save((int) $id, $_POST, $_FILES['proxy_doc'] ?? null);
        } catch (ValidationException $e) {
            fail($e, "/meetings/$id/edit");
        }
        flash('success', __('app.saved'));
        redirect("/meetings/$id");
    }

    public function show(string $id): void
    {
        $m = $this->load($id);
        $shareholders = MeetingService::shareholders((int) $id);
        View::render('Meetings::show', [
            'title'        => __('meetings.event_title', ['client' => $m['client_name']]),
            'm'            => $m,
            'shareholders' => $shareholders,
            'summary'      => MeetingService::summary($shareholders),
        ]);
    }

    public function toggle(string $id): void
    {
        $this->load($id);
        MeetingService::toggleOpen((int) $id);
        back();
    }

    public function reset(string $id, string $shareholder): void
    {
        $this->load($id);
        MeetingService::resetResponse((int) $id, (int) $shareholder);
        flash('success', __('meetings.response_reset'));
        redirect("/meetings/$id");
    }

    public function delete(string $id): void
    {
        $m = $this->load($id);
        MeetingService::delete((int) $id);
        flash('success', __('app.deleted'));
        redirect('/clients/' . $m['client_id']);
    }

    public function csv(string $id): void
    {
        MeetingService::csv($this->load($id));
    }

    /** Yüklenen vekaletname şablonu/örneği. */
    public function doc(string $id): void
    {
        $m = $this->load($id);
        $this->send(MeetingService::docPath($m['proxy_doc_file']) ?? abort(404), (string) $m['proxy_doc_name']);
    }

    /** Bir pay sahibinin doldurulmuş vekaletnamesi. */
    public function proxy(string $id, string $shareholder): void
    {
        $m = $this->load($id);
        $s = array_values(array_filter(MeetingService::shareholders((int) $id), fn ($s) => (int) $s['id'] === (int) $shareholder))[0] ?? abort(404);
        [$path, $name] = MeetingService::filledProxy($m, $s) ?? abort(404);
        $this->send($path, $name, true);
    }

    /** Excel/CSV'den pay sahibi satırları (forma eklenir). */
    public function import(): void
    {
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['xlsx', 'csv', 'txt'], true)) {
            json_response(['error' => __('meetings.import_type')], 422);
        }
        $lines = MeetingService::importLines(Spreadsheet::rows($file['tmp_name'], $file['name']));
        json_response($lines ? ['lines' => $lines] : ['error' => __('meetings.import_empty')], $lines ? 200 : 422);
    }

    /** Genel kurul, müvekkili görünen kullanıcıya açılır. */
    private function load(string $id): array
    {
        $m = MeetingService::find((int) $id);
        return $m && \App\Services\Access::canSeeClient((int) $m['client_id']) ? $m : abort(404);
    }

    private function form(?array $m): void
    {
        $clientId = (int) ($m['client_id'] ?? $_GET['client_id'] ?? old('client_id', '0'));
        View::render('Meetings::form', [
            'title'    => __($m ? 'meetings.edit' : 'meetings.new'),
            'm'        => $m,
            'clientId' => $clientId,
            'clients'  => $m ? [] : DB::all('SELECT c.id, c.code, c.name FROM clients c WHERE c.is_active = 1 AND ' . \App\Services\Access::clientWhere()[0] . ' ORDER BY c.name', \App\Services\Access::clientWhere()[1]),
            'projects' => DB::all('SELECT p.id, p.client_id, p.number, p.name FROM projects p WHERE (p.status <> \'closed\' AND ' . \App\Services\Access::projectWhere()[0] . ') OR p.id = ? ORDER BY p.number', array_merge(\App\Services\Access::projectWhere()[1], [(int) ($m['project_id'] ?? 0)])),
            'lines'    => $m ? implode("\n", array_map([MeetingService::class, 'line'], MeetingService::shareholders((int) $m['id']))) : '',
        ]);
    }

    private function send(string $path, string $name, bool $temp = false): never
    {
        $docx = str_ends_with($path, '.docx');
        header('Content-Type: ' . ($docx ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf'));
        header('Content-Disposition: ' . ($docx ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        if ($temp) {
            unlink($path);
        }
        exit;
    }
}
