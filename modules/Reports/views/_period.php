<?php use App\Services\ReportService; ?>
<select name="period" class="toolbar-select" onchange="this.form.querySelector('.custom-range').hidden = this.value !== 'custom'; if (this.value !== 'custom') this.form.submit()">
    <?php foreach (ReportService::PERIODS as $p): ?>
        <option value="<?= $p ?>" <?= $filters['period'] === $p ? 'selected' : '' ?>><?= e(__('reports.period_' . $p)) ?></option>
    <?php endforeach; ?>
</select>
<span class="custom-range" <?= $filters['period'] === 'custom' ? '' : 'hidden' ?>>
    <input type="date" name="from" value="<?= e($filters['from']) ?>" class="toolbar-select">
    <span class="muted">–</span>
    <input type="date" name="to" value="<?= e($filters['to']) ?>" class="toolbar-select">
    <button class="btn btn-sm btn-primary"><?= e(__('reports.apply')) ?></button>
</span>
<span class="muted small period-label"><?= e(format_date($filters['from'])) ?> – <?= e(format_date($filters['to'])) ?></span>
