<?php

declare(strict_types=1);

namespace Modules\Calendar;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\CalendarService;
use App\Services\TimeService;

final class CalendarController
{
    /** Ajanda görünümü: ay ay, gün gruplu; üstte gecikmiş süreler. */
    public function index(): void
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
        $mine = ($_GET['mine'] ?? '1') === '1';
        $userId = $mine ? Auth::id() : null;
        $from = "$month-01";
        $to = date('Y-m-t', strtotime($from));

        $days = [];
        foreach (CalendarService::range($from, $to, $userId) as $e) {
            $days[substr($e['starts_at'], 0, 10)][] = $e;
        }
        View::render('Calendar::index', [
            'title'   => __('cal.title'),
            'month'   => $month,
            'mine'    => $mine,
            'days'    => $days,
            'overdue' => CalendarService::overdue($userId),
        ]);
    }

    public function show(string $id): void
    {
        $event = CalendarService::find((int) $id) ?? abort(404);
        View::render('Calendar::show', ['title' => $event['title'], 'e' => $event]);
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $this->form(CalendarService::find((int) $id) ?? abort(404));
    }

    public function store(): void
    {
        try {
            $id = CalendarService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, '/calendar/new' . (isset($_POST['project_id']) ? '?project_id=' . (int) $_POST['project_id'] : ''));
        }
        flash('success', __('cal.saved'));
        redirect("/calendar/$id");
    }

    public function update(string $id): void
    {
        CalendarService::find((int) $id) ?? abort(404);
        try {
            CalendarService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/calendar/$id/edit");
        }
        flash('success', __('cal.saved'));
        redirect("/calendar/$id");
    }

    public function delete(string $id): void
    {
        $e = CalendarService::find((int) $id) ?? abort(404);
        CalendarService::delete((int) $id);
        flash('success', __('cal.deleted'));
        redirect($e['project_id'] ? "/projects/{$e['project_id']}" : '/calendar');
    }

    public function done(string $id): void
    {
        CalendarService::find((int) $id) ?? abort(404);
        CalendarService::toggleDone((int) $id);
        back();
    }

    /** Outlook'a yansıtılamayan kopyaları yeniden dener. */
    public function resync(string $id): void
    {
        CalendarService::find((int) $id) ?? abort(404);
        CalendarService::sync((int) $id);
        back();
    }

    private function form(?array $event): void
    {
        $projectId = (int) ($event['project_id'] ?? $_GET['project_id'] ?? 0);
        // Yeni kayıtta katılımcı varsayılanı: kendisi + (proje seçiliyse) proje ekibi
        $attendees = $event ? array_map('intval', array_column($event['attendees'], 'user_id')) : [(int) Auth::id()];
        if (!$event && $projectId) {
            $attendees = array_merge($attendees, array_map('intval', array_column(
                DB::all('SELECT user_id FROM project_members WHERE project_id = ?', [$projectId]), 'user_id'
            )));
        }
        View::render('Calendar::form', [
            'title'     => __($event ? 'cal.edit' : 'cal.new'),
            'e'         => $event,
            'projectId' => $projectId,
            'attendees' => array_unique($attendees),
            'projects'  => TimeService::projectOptions(Auth::id()),
            'users'     => DB::all('SELECT id, name, title FROM users WHERE is_active = 1 ORDER BY name'),
        ]);
    }
}
