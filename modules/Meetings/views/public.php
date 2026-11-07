<?php
use App\Core\Lang;
use App\Core\View;
use App\Services\MeetingService;
use App\Services\Settings;

$orgName = (string) Settings::get('org.name', config('app.name'));
$hasDefault = $m && $m['default_proxy_name'];
$isDocx = $m && MeetingService::isDocx($m);
$meetingText = '';
if ($m) {
    $ts = strtotime($m['meeting_at']);
    $meetingText = format_date(substr($m['meeting_at'], 0, 10)) . ' · ' . __('cal.dow_' . date('N', $ts)) . ' · ' . date('H:i', $ts);
}
$checked = fn (string $k, string $v) => old($k) === $v ? 'checked' : '';
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($m ? $m['client_name'] . ' · ' : '') . __('gk.title')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <?= View::partial('_theme') ?>
</head>
<body class="gk-body">
<div class="gk-wrap">
    <div class="gk-top">
        <?php if (Settings::get('org.logo')): ?>
            <img src="/logo" alt="<?= e($orgName) ?>" class="brand-logo">
        <?php else: ?>
            <span class="brand"><span class="brand-mark"><?= e(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></span><?= e($orgName) ?></span>
        <?php endif; ?>
        <div class="lang-switch">
            <?php foreach (config('app.locales') as $loc): ?>
                <a href="?lang=<?= $loc ?>" class="<?= Lang::locale() === $loc ? 'active' : '' ?>"><?= strtoupper($loc) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($state === 'notfound'): ?>
        <div class="card gk-message">
            <h1><?= e(__('gk.notfound')) ?></h1>
            <p class="muted"><?= e(__('gk.notfound_hint')) ?></p>
        </div>
    <?php elseif ($state === 'closed'): ?>
        <div class="card gk-message">
            <h1><?= e($m['client_name']) ?></h1>
            <p class="muted"><?= e(__('gk.closed')) ?></p>
        </div>
    <?php elseif ($state === 'done'): ?>
        <div class="card gk-message">
            <span class="gk-check"><?= icon('check') ?></span>
            <h1><?= e(__('gk.done')) ?></h1>
            <?php if ($proxyUrl): ?>
                <p class="muted"><?= e(__('gk.done_proxy')) ?></p>
                <a href="<?= e($proxyUrl) ?>" class="btn btn-primary btn-block"><?= e(__('gk.download_proxy')) ?></a>
                <p class="muted small" style="margin-top:16px"><?= e(__('gk.proxy_notary')) ?></p>
            <?php else: ?>
                <p class="muted"><?= e(__('gk.done_hint')) ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card gk-card">
            <div class="gk-head">
                <span><?= e(__('gk.title')) ?></span>
                <h1><?= e($m['client_name']) ?></h1>
                <p><?= icon('calendar') ?> <?= e($meetingText) ?><?= $m['location'] ? ' · ' . e($m['location']) : '' ?></p>
            </div>
            <div class="gk-agenda">
                <h2><?= e(__('gk.agenda')) ?></h2>
                <div><?= nl2br(e($m['agenda'])) ?></div>
            </div>

            <form method="post" class="form gk-form" id="gk-form">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="gk-step"><?= e(__('gk.step_name')) ?></span>
                    <select name="shareholder_id" required>
                        <option value=""><?= e(__('gk.choose')) ?></option>
                        <?php foreach ($shareholders as $s): ?>
                            <option value="<?= $s['id'] ?>" data-needs-shares="<?= (int) $s['needs_shares'] ?>" <?= $s['answered'] ? 'disabled' : '' ?> <?= old('shareholder_id') === (string) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['full_name']) ?><?= $s['answered'] ? ' (' . e(__('gk.answered')) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_for('shareholder_id') ?>
                </label>

                <fieldset class="field">
                    <span class="gk-step"><?= e(__('gk.step_attendance')) ?></span>
                    <?php foreach (MeetingService::ATTENDANCE as $a): ?>
                        <label class="gk-option">
                            <input type="radio" name="attendance" value="<?= $a ?>" required <?= $checked('attendance', $a) ?>>
                            <span><strong><?= e(__('meetings.att_' . $a)) ?></strong><small><?= e(__('gk.att_' . $a . '_hint')) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                    <?= error_for('attendance') ?>
                </fieldset>

                <div class="gk-note" data-show="representative"><?= e(__('gk.note_representative')) ?></div>
                <div class="gk-note" data-show="proxy">
                    <ol>
                        <li><?= e(__('gk.note_proxy_1')) ?></li>
                        <li><?= e(__('gk.note_proxy_2')) ?></li>
                        <li><?= e(__('gk.note_proxy_3')) ?></li>
                    </ol>
                </div>

                <div class="gk-box" data-show="proxy">
                    <strong><?= e(__('gk.owner_info')) ?></strong>
                    <small class="muted"><?= e(__('gk.owner_info_hint')) ?></small>
                    <label class="field">
                        <span><?= e(__('meetings.id_no')) ?></span>
                        <input name="sh_id_no" inputmode="numeric" maxlength="11" autocomplete="off" value="<?= e(old('sh_id_no')) ?>">
                        <?= error_for('sh_id_no') ?>
                    </label>
                    <div class="form-grid" data-shares>
                        <label class="field">
                            <span><?= e(__('meetings.shares')) ?></span>
                            <input name="sh_share_count" inputmode="numeric" maxlength="60" autocomplete="off" value="<?= e(old('sh_share_count')) ?>">
                            <?= error_for('sh_share_count') ?>
                        </label>
                        <label class="field">
                            <span><?= e(__('meetings.share_group')) ?> <span class="muted">(<?= e(__('gk.if_any')) ?>)</span></span>
                            <input name="sh_share_group" maxlength="60" autocomplete="off" value="<?= e(old('sh_share_group')) ?>">
                        </label>
                    </div>
                </div>

                <div class="gk-box" data-show="proxy">
                    <strong><?= e(__('gk.proxy_info')) ?></strong>
                    <?php if ($hasDefault): ?>
                        <label class="gk-option">
                            <input type="radio" name="proxy_choice" value="default" <?= $checked('proxy_choice', 'default') ?>>
                            <span><strong><?= e($m['default_proxy_name']) ?></strong><small><?= e(__('gk.default_proxy_hint')) ?></small></span>
                        </label>
                        <label class="gk-option">
                            <input type="radio" name="proxy_choice" value="custom" <?= $checked('proxy_choice', 'custom') ?>>
                            <span><strong><?= e(__('gk.custom_proxy')) ?></strong><small><?= e(__('gk.custom_proxy_hint')) ?></small></span>
                        </label>
                        <?= error_for('proxy_choice') ?>
                    <?php endif; ?>
                    <div data-custom>
                        <label class="field">
                            <span><?= e(__('gk.proxy_name')) ?></span>
                            <input name="proxy_name" maxlength="255" autocomplete="off" value="<?= e(old('proxy_name')) ?>">
                            <?= error_for('proxy_name') ?>
                        </label>
                        <label class="field">
                            <span><?= e(__('meetings.proxy_tc')) ?></span>
                            <input name="proxy_tc" inputmode="numeric" maxlength="11" autocomplete="off" value="<?= e(old('proxy_tc')) ?>">
                            <?= error_for('proxy_tc') ?>
                        </label>
                        <label class="field">
                            <span><?= e(__('meetings.proxy_address')) ?></span>
                            <textarea name="proxy_address" rows="2" maxlength="500"><?= e(old('proxy_address')) ?></textarea>
                            <?= error_for('proxy_address') ?>
                        </label>
                    </div>
                    <?php if ($m['proxy_doc_file'] && !$isDocx): ?>
                        <a href="/gk/<?= e($token) ?>/doc" target="_blank" class="btn btn-outline btn-block"><?= e(__('gk.view_sample')) ?> ↗</a>
                    <?php elseif ($isDocx): ?>
                        <small class="muted"><?= e(__('gk.docx_after')) ?></small>
                    <?php endif; ?>
                </div>

                <button class="btn btn-primary btn-block"><?= e(__('gk.submit')) ?></button>
            </form>
        </div>
    <?php endif; ?>
    <p class="gk-foot"><?= e($orgName) ?></p>
</div>

<?php if ($state === 'form'): ?>
<script>
(() => {
    const f = document.getElementById('gk-form');
    const sync = () => {
        const att = (f.querySelector('[name=attendance]:checked') || {}).value;
        f.querySelectorAll('[data-show]').forEach(el => el.hidden = el.dataset.show !== att);
        const opt = f.shareholder_id.selectedOptions[0];
        f.querySelector('[data-shares]').hidden = !(opt && opt.dataset.needsShares === '1');
        const choice = (f.querySelector('[name=proxy_choice]:checked') || {}).value;
        const custom = f.querySelector('[data-custom]');
        custom.hidden = !!f.querySelector('[name=proxy_choice]') && choice !== 'custom';
    };
    f.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
<script>window.FILEPICK = <?= json_encode(['choose' => __('file.choose'), 'drop' => __('file.drop'), 'remove' => __('file.remove')], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('filepick.js') ?>"></script>
</body>
</html>
