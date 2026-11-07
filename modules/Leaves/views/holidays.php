<div class="with-subnav">
    <?= \App\Core\View::partial('Admin::_nav') ?>
    <div>
        <div class="page-head">
            <div>
                <h1><?= e(__('holidays.title')) ?></h1>
                <p class="muted"><?= e(__('holidays.subtitle')) ?></p>
            </div>
            <div class="month-nav">
                <a href="?year=<?= $year - 1 ?>" class="btn btn-sm btn-outline" aria-label="‹">‹</a>
                <strong><?= $year ?></strong>
                <a href="?year=<?= $year + 1 ?>" class="btn btn-sm btn-outline" aria-label="›">›</a>
            </div>
        </div>

        <form method="post" action="/settings/holidays" class="card form-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field">
                    <span><?= e(__('time.date')) ?> *</span>
                    <input type="date" name="date" value="<?= e(old('date')) ?>" required>
                    <?= error_for('date') ?>
                </label>
                <label class="field">
                    <span><?= e(__('holidays.name')) ?> *</span>
                    <input name="name" value="<?= e(old('name')) ?>" required placeholder="<?= e(__('holidays.name_hint')) ?>">
                    <?= error_for('name') ?>
                </label>
            </div>
            <label class="check"><input type="checkbox" name="half_day" value="1"><span><?= e(__('holidays.half_day')) ?></span></label>
            <div class="form-actions form-actions-tight">
                <button class="btn btn-primary"><?= e(__('holidays.add')) ?></button>
            </div>
        </form>

        <div class="card">
            <div class="toolbar">
                <strong><?= e(__('holidays.list', ['year' => $year])) ?></strong>
                <form method="post" action="/settings/holidays/fixed">
                    <?= csrf_field() ?>
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <button class="btn btn-sm btn-outline"><?= icon('plus') ?><?= e(__('holidays.add_fixed')) ?></button>
                </form>
            </div>
            <?php if (!$holidays): ?>
                <div class="empty empty-lg"><?= e(__('holidays.empty')) ?></div>
            <?php else: ?>
                <table class="table">
                    <tbody>
                    <?php foreach ($holidays as $h): ?>
                        <tr>
                            <td class="nowrap"><?= e(format_date($h['date'])) ?> <span class="muted small"><?= e(__('cal.dow_' . date('N', strtotime($h['date'])))) ?></span></td>
                            <td><?= e($h['name']) ?><?php if ($h['half_day']): ?> <span class="badge badge-gray"><?= e(__('holidays.half')) ?></span><?php endif; ?></td>
                            <td class="cell-actions">
                                <form method="post" action="/settings/holidays/<?= e($h['date']) ?>/delete" onsubmit="return confirm('<?= e(__('app.confirm_delete')) ?>')">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-outline btn-danger"><?= e(__('app.delete')) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="muted small" style="padding:10px 16px"><?= e(__('holidays.religious_hint')) ?></p>
        </div>
    </div>
</div>
