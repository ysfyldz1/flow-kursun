<?php
use App\Services\TransferService;

$text = [
    'files'        => __('transfers.js_files'),
    'skipped'      => __('transfers.js_skipped'),
    'preparing'    => __('transfers.js_preparing'),
    'uploading'    => __('transfers.js_uploading'),
    'finishing'    => __('transfers.js_finishing'),
    'retryFailed'  => __('transfers.js_retry_failed'),
    'badResponse'  => __('transfers.js_bad_response'),
    'halfDone'     => __('transfers.js_half_done'),
    'emailed'      => __('transfers.js_emailed'),
    'emailFailed'  => __('transfers.js_email_failed'),
    'share'        => __('transfers.js_share'),
    'copied'       => __('transfers.copied'),
    'remove'       => __('file.remove'),
];
?>
<div class="page-head">
    <div>
        <h1><?= e(__('transfers.new')) ?></h1>
        <p class="muted"><a href="/transfers"><?= e(__('transfers.back')) ?></a></p>
    </div>
</div>

<?php if (!TransferService::enabled()): ?>
    <div class="card card-body"><?= e(__('transfers.not_configured')) ?></div>
<?php else: ?>
<div class="tf-wrap" id="tf-new" data-text="<?= e(json_encode($text, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="grid grid-3-1" id="tf-form">
        <section class="card form-card">
            <div class="tf-drop" id="tf-drop">
                <span class="filepick-icon"><?= icon('download') ?></span>
                <strong><?= e(__('transfers.drop')) ?></strong>
                <small class="muted"><?= e(__('transfers.drop_hint')) ?></small>
                <div class="tf-pick">
                    <label class="btn btn-outline btn-sm"><?= e(__('transfers.pick_files')) ?><input type="file" id="tf-files" multiple hidden data-plain></label>
                    <label class="btn btn-outline btn-sm"><?= e(__('transfers.pick_folder')) ?><input type="file" id="tf-folder" webkitdirectory multiple hidden data-plain></label>
                </div>
            </div>
            <div class="card-title-row tf-list-head">
                <h3 class="card-title"><?= e(__('transfers.files')) ?></h3>
                <span class="muted small" id="tf-summary"></span>
            </div>
            <div class="tf-list" id="tf-list"><div class="empty" id="tf-empty"><?= e(__('transfers.no_files')) ?></div></div>
        </section>

        <section class="card form-card">
            <label class="field">
                <span><?= e(__('transfers.field_title')) ?></span>
                <input id="tf-title" maxlength="255">
                <small class="muted"><?= e(__('transfers.field_title_hint')) ?></small>
            </label>
            <label class="field">
                <span><?= e(__('transfers.recipient')) ?></span>
                <input id="tf-email" placeholder="ad@ornek.com">
                <small class="muted"><?= e(__('transfers.recipient_hint')) ?></small>
            </label>
            <label class="field">
                <span><?= e(__('transfers.message')) ?></span>
                <textarea id="tf-message" rows="3"></textarea>
            </label>
            <label class="field">
                <span><?= e(__('transfers.expiry')) ?></span>
                <select id="tf-expiry">
                    <?php foreach (TransferService::EXPIRY_DAYS as $d): ?>
                        <option value="<?= $d ?>"><?= e(__('transfers.expiry_' . $d)) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="muted"><?= e(__('transfers.expiry_hint', ['days' => TransferService::RETENTION_DAYS])) ?></small>
            </label>
            <label class="field">
                <span><?= e(__('transfers.password')) ?></span>
                <input id="tf-password" autocomplete="off">
            </label>
            <label class="field">
                <span><?= e(__('transfers.locale')) ?></span>
                <select id="tf-locale">
                    <?php foreach (config('app.locales') as $loc): ?>
                        <option value="<?= $loc ?>" <?= $locale === $loc ? 'selected' : '' ?>><?= e(__('lang.' . $loc)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check">
                <input type="checkbox" id="tf-notify" checked>
                <span><?= e(__('transfers.notify')) ?></span>
            </label>

            <div id="tf-progress" hidden>
                <div class="tf-progress-text"><span id="tf-label"></span><span id="tf-pct">0%</span></div>
                <div class="tf-bar"><div id="tf-bar"></div></div>
            </div>
            <div class="tf-error" id="tf-error" hidden></div>

            <button type="button" class="btn btn-primary btn-block" id="tf-send" disabled><?= icon('send') ?><?= e(__('transfers.send')) ?></button>
        </section>
    </div>

    <section class="card gk-message tf-done" id="tf-done" hidden>
        <span class="gk-check"><?= icon('check') ?></span>
        <h1><?= e(__('transfers.done')) ?></h1>
        <p class="muted" id="tf-done-note"></p>
        <div class="tf-link">
            <input id="tf-url" readonly onclick="this.select()">
            <button type="button" class="btn btn-primary" data-copy="#tf-url"><?= e(__('transfers.copy')) ?></button>
        </div>
        <p><a href="#" id="tf-detail"><?= e(__('transfers.go_detail')) ?></a></p>
    </section>
</div>
<script src="<?= asset('transfer.js') ?>" defer></script>
<?php endif; ?>
