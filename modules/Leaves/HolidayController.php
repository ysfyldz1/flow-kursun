<?php

declare(strict_types=1);

namespace Modules\Leaves;

use App\Core\ValidationException;
use App\Core\View;
use App\Services\LeaveService;

final class HolidayController
{
    public function index(): void
    {
        $year = (int) ($_GET['year'] ?? date('Y'));
        View::render('Leaves::holidays', [
            'title'    => __('holidays.title'),
            'year'     => $year,
            'holidays' => LeaveService::holidays($year),
        ]);
    }

    public function store(): void
    {
        try {
            LeaveService::saveHoliday($_POST);
        } catch (ValidationException $e) {
            fail($e, '/settings/holidays?year=' . (int) substr((string) ($_POST['date'] ?? ''), 0, 4));
        }
        flash('success', __('app.saved'));
        redirect('/settings/holidays?year=' . (int) substr($_POST['date'], 0, 4));
    }

    public function fixed(): void
    {
        $year = (int) ($_POST['year'] ?? date('Y'));
        $added = LeaveService::addFixedHolidays($year);
        flash('success', __('holidays.fixed_added', ['count' => $added]));
        redirect("/settings/holidays?year=$year");
    }

    public function delete(string $date): void
    {
        LeaveService::deleteHoliday($date);
        flash('success', __('app.deleted'));
        redirect('/settings/holidays?year=' . (int) substr($date, 0, 4));
    }
}
