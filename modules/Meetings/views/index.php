<div class="page-head">
    <div>
        <h1><?= e(__('meetings.title')) ?></h1>
        <p class="muted"><?= e(__('meetings.subtitle')) ?></p>
    </div>
    <a href="/meetings/new" class="btn btn-primary"><?= icon('plus') ?><?= e(__('meetings.new')) ?></a>
</div>

<div class="card">
    <div class="toolbar">
        <div class="tabs">
            <a href="?when=upcoming" class="<?= $when === 'upcoming' ? 'active' : '' ?>"><?= e(__('meetings.upcoming')) ?></a>
            <a href="?when=past" class="<?= $when === 'past' ? 'active' : '' ?>"><?= e(__('meetings.past')) ?></a>
        </div>
    </div>
    <?php if (!$rows): ?>
        <div class="empty empty-lg"><?= e(__('meetings.empty')) ?></div>
    <?php else: ?>
        <table class="table table-m">
            <thead>
                <tr>
                    <th><?= e(__('meetings.company')) ?></th>
                    <th><?= e(__('meetings.date')) ?></th>
                    <th class="num"><?= e(__('meetings.responses')) ?></th>
                    <th><?= e(__('users.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $m): ?>
                <tr class="row-link" onclick="location.href='/meetings/<?= $m['id'] ?>'">
                    <td class="cell-main m-title">
                        <a href="/meetings/<?= $m['id'] ?>"><strong><?= e($m['client_name']) ?></strong></a>
                        <span><?= e($m['client_code']) ?><?= $m['project_number'] ? ' · ' . e($m['project_number']) : '' ?></span>
                    </td>
                    <td class="nowrap m-meta"><?= e(format_date($m['meeting_at'], true)) ?></td>
                    <td class="num m-meta"><?= (int) $m['response_count'] ?> / <?= (int) $m['shareholder_count'] ?></td>
                    <td class="m-value"><?= \App\Core\View::partial('Meetings::_status', ['m' => $m]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
