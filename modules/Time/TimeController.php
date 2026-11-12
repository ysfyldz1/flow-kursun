<?php

declare(strict_types=1);

namespace Modules\Time;

use App\Core\Auth;
use App\Core\DB;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\TimeService;

final class TimeController
{
    public function __construct()
    {
        if (!can('time.own') && !can('time.all')) {
            abort(403);
        }
    }

    public function index(): void
    {
        // Hafta: ?week=2026-11-02 (pazartesi); başkasının haftası yalnızca time.all ile
        $monday = date('Y-m-d', strtotime('monday this week', strtotime((string) ($_GET['week'] ?? 'today')) ?: time()));
        $userId = can('time.all') && !empty($_GET['user']) ? (int) $_GET['user'] : Auth::id();
        $viewUser = DB::one('SELECT id, name FROM users WHERE id = ?', [$userId]) ?? abort(404);

        $entries = TimeService::week($userId, $monday);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = date('Y-m-d', strtotime("$monday +$i day"));
            $days[$day] = ['entries' => [], 'minutes' => 0];
        }
        foreach ($entries as $e) {
            $days[$e['work_date']]['entries'][] = $e;
            $days[$e['work_date']]['minutes'] += (int) $e['minutes'];
        }

        View::render('Time::index', [
            'title'    => __('time.title'),
            'monday'   => $monday,
            'days'     => $days,
            'entries'  => $entries,
            'viewUser' => $viewUser,
            'isSelf'   => $userId === Auth::id(),
            'users'    => can('time.all') ? DB::all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name') : [],
            'projects' => TimeService::projectOptions($userId),
            'timer'    => TimeService::timer(Auth::id()),
            'entry'    => null,
        ]);
    }

    public function store(): void
    {
        try {
            $id = TimeService::create($_POST);
        } catch (ValidationException $e) {
            fail($e, $this->back());
        }
        $entry = TimeService::find($id);
        // Kayıt engellenmez; onaylı izinli güne girildiyse not düşülür
        flash('success', __('time.saved') . (\App\Services\LeaveService::isOnLeave((int) $entry['user_id'], $entry['work_date']) ? ' ' . __('leaves.time_on_leave') : ''));
        $this->phaseWarning($entry);
        // Aynı güne art arda giriş kolay olsun diye tarih ve proje hatırlanır
        $_SESSION['_time_last'] = ['work_date' => $_POST['work_date'] ?? '', 'project_id' => $_POST['project_id'] ?? '', 'phase_id' => $_POST['phase_id'] ?? ''];
        redirect($this->back());
    }

    public function edit(string $id): void
    {
        $entry = TimeService::find((int) $id) ?? abort(404);
        if (!TimeService::canModify($entry)) {
            abort(403);
        }
        View::render('Time::edit', [
            'title'    => __('time.edit'),
            'entry'    => $entry,
            'projects' => TimeService::projectOptions((int) $entry['user_id']),
        ]);
    }

    public function update(string $id): void
    {
        try {
            TimeService::update((int) $id, $_POST);
        } catch (ValidationException $e) {
            fail($e, "/time/$id/edit");
        }
        flash('success', __('app.saved'));
        $entry = TimeService::find((int) $id);
        $this->phaseWarning($entry);
        redirect('/time?week=' . $entry['work_date'] . ((int) $entry['user_id'] !== Auth::id() ? '&user=' . $entry['user_id'] : ''));
    }

    public function delete(string $id): void
    {
        try {
            TimeService::delete((int) $id);
            flash('success', __('app.deleted'));
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors));
        }
        redirect($this->back());
    }

    public function startTimer(): void
    {
        try {
            TimeService::startTimer(Auth::id(), (int) ($_POST['timer_project_id'] ?? 0), $_POST['timer_description'] ?? null);
        } catch (ValidationException $e) {
            fail($e, $this->back());
        }
        redirect($this->back());
    }

    public function stopTimer(): void
    {
        try {
            TimeService::stopTimer(Auth::id(), (string) ($_POST['timer_description'] ?? ''), !empty($_POST['is_billable']));
        } catch (ValidationException $e) {
            // Açıklama boşsa sayaç çalışmaya devam eder, hata gösterilir
            fail(new ValidationException(['timer_description' => $e->errors['description'] ?? implode(' ', $e->errors)]), $this->back());
        }
        flash('success', __('time.saved'));
        redirect($this->back());
    }

    public function discardTimer(): void
    {
        TimeService::discardTimer(Auth::id());
        redirect($this->back());
    }

    /** Kurşun: aşama bütçesi eşiği geçtiyse ya da aşıldıysa kayıt engellenmez, uyarı gösterilir. */
    private function phaseWarning(array $entry): void
    {
        if ($entry['phase_id'] && ($w = \App\Services\PhaseService::warningFor((int) $entry['phase_id']))) {
            flash('warning', $w);
        }
    }

    private function back(): string
    {
        $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '/time';
        $query = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
        return $ref . ($query ? '?' . $query : '');
    }
}
