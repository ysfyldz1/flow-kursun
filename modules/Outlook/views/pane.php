<?php
// Outlook paneli. Görünüm ve akış public/assets/outlook.js'te; metinler buradan JSON olarak verilir.
$primary = (string) \App\Services\Settings::get('org.primary_color', '#1e3a8a');
$keys = ['connect_title', 'connect_hint', 'connect', 'connect_failed', 'disconnect', 'loading', 'saved_here', 'thread_hint',
         'suggested', 'recent', 'search', 'search_hint', 'no_results', 'note', 'note_hint', 'attachments', 'attachments_hint',
         'add_time', 'duration', 'duration_hint', 'billable', 'time_desc', 'save', 'saving', 'saved_ok', 'already_saved',
         'attachments_saved', 'time_saved', 'open_project', 'save_another', 'compose_hint', 'queue', 'queued', 'unqueue',
         'compose_unsupported', 'not_connected_ms', 'error', 'pick_project', 'change'];
$t = [];
foreach ($keys as $k) {
    $t[$k] = __('outlook.' . $k);
}
?>
<!doctype html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flow</title>
    <script src="https://appsforoffice.microsoft.com/lib/1/hosted/office.js"></script>
    <style>
        :root { --primary: <?= e($primary) ?>; --text: #0f172a; --muted: #64748b; --border: #e6e9ef; --bg: #f4f6f9; --green: #16a34a; --red: #dc2626; --amber: #d97706; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 13px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--text); background: #fff; }
        main { padding: 14px; }
        h2 { font-size: 13px; font-weight: 600; margin: 16px 0 6px; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
        .muted { color: var(--muted); }
        .small { font-size: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text); font: inherit; font-weight: 600; cursor: pointer; }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; width: 100%; height: 38px; }
        .btn[disabled] { opacity: .6; cursor: default; }
        .link { background: none; border: 0; padding: 0; color: var(--primary); font: inherit; cursor: pointer; text-decoration: underline; }
        .banner { padding: 10px 12px; border-radius: 8px; background: #ecfdf3; color: #166534; margin-bottom: 10px; }
        .banner.info { background: #eef3ff; color: #1e40af; }
        .banner.error { background: #fef1f1; color: var(--red); }
        .projects { display: flex; flex-direction: column; gap: 6px; }
        .project { display: flex; gap: 10px; align-items: flex-start; padding: 9px 10px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; }
        .project:hover { border-color: #cbd5e1; }
        .project input { margin-top: 3px; accent-color: var(--primary); }
        .project.selected { border-color: var(--primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 18%, transparent); }
        .project strong { display: block; font-weight: 600; }
        .project span { color: var(--muted); font-size: 12px; }
        input[type=text], input[type=search], textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; font: inherit; color: inherit; }
        textarea { resize: vertical; min-height: 54px; }
        label.check { display: flex; gap: 8px; align-items: flex-start; margin: 6px 0; cursor: pointer; }
        label.check input { margin-top: 3px; accent-color: var(--primary); }
        .row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; align-items: center; margin-top: 6px; }
        .field-hint { font-size: 11px; color: var(--muted); margin-top: 3px; }
        .error-text { color: var(--red); font-size: 12px; margin-top: 4px; }
        .done { text-align: center; padding: 18px 6px; }
        .done .tick { font-size: 32px; color: var(--green); }
        .actions { margin-top: 16px; }
        .user { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); margin-top: 18px; padding-top: 10px; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main id="app"><p class="muted"><?= e(__('outlook.loading')) ?></p></main>
<script>
window.FLOW = <?= json_encode(['t' => $t, 'org' => $org, 'decimal' => $decimal, 'locale' => \App\Core\Lang::locale()], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('outlook.js') ?>"></script>
</body>
</html>
