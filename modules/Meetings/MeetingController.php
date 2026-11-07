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
        $this->form(MeetingService::find((int) $id) ?? abort(404));
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
        MeetingService::find((int) $id) ?? abort(404);
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
        $m = MeetingService::find((int) $id) ?? abort(404);
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
        MeetingService::find((int) $id) ?? abort(404);
        MeetingService::toggleOpen((int) $id);
        back();
    }

    public function reset(string $id, string $shareholder): void
    {
        MeetingService::find((int) $id) ?? abort(404);
        MeetingService::resetResponse((int) $id, (int) $shareholder);
        flash('success', __('meetings.response_reset'));
        redirect("/meetings/$id");
    }

    public function delete(string $id): void
    {
        $m = MeetingService::find((int) $id) ?? abort(404);
        MeetingService::delete((int) $id);
        flash('success', __('app.deleted'));
        redirect('/clients/' . $m['client_id']);
    }

    public function csv(string $id): void
    {
        MeetingService::csv(MeetingService::find((int) $id) ?? abort(404));
    }

    /** Yüklenen vekaletname şablonu/örneği. */
    public function doc(string $id): void
    {
        $m = MeetingService::find((int) $id) ?? abort(404);
        $this->send(MeetingService::docPath($m['proxy_doc_file']) ?? abort(404), (string) $m['proxy_doc_name']);
    }

    /** Bir pay sahibinin doldurulmuş vekaletnamesi. */
    public function proxy(string $id, string $shareholder): void
    {
        $m = MeetingService::find((int) $id) ?? abort(404);
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

    private function form(?array $m): void
    {
        $clientId = (int) ($m['client_id'] ?? $_GET['client_id'] ?? old('client_id', '0'));
        View::render('Meetings::form', [
            'title'    => __($m ? 'meetings.edit' : 'meetings.new'),
            'm'        => $m,
            'clientId' => $clientId,
            'clients'  => $m ? [] : DB::all("SELECT id, code, name FROM clients WHERE is_active = 1 ORDER BY name"),
            'projects' => DB::all('SELECT id, client_id, number, name FROM projects WHERE status <> \'closed\' OR id = ? ORDER BY number', [(int) ($m['project_id'] ?? 0)]),
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
