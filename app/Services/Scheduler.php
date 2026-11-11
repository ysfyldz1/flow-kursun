<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Zamanlanmış işler. Sunucuda tek cron satırı saatte bir bin/cron.php'yi çalıştırır; hangi işin ne zaman
 * yapılacağına burası karar verir. Yeni iş JOBS'a eklenir, cPanel'e dokunulmaz.
 * Sunucu bir çalışmayı kaçırırsa bir sonraki saat telafi eder; günlük iş aynı gün ikinci kez çalışmaz.
 */
final class Scheduler
{
    /** ad => [sıklık (daily|hourly), en erken saat] */
    public const JOBS = [
        'reminders' => ['daily', 7],
        'transfers' => ['daily', 3],
        'outlook'   => ['hourly', 0],   // "gönderince kaydet": Gönderilmiş Öğeler'de bulunan e-postalar projeye
    ];

    /** Son çalışma bundan eskiyse cron durmuş sayılır (saat) */
    public const STALE_HOURS = 3;

    public static function run(?int $now = null): array
    {
        $now ??= time();
        $lock = fopen(BASE_PATH . '/storage/cron.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['_' => 'önceki çalışma sürüyor'];   // uzun süren iş üst üste binmesin
        }
        $out = [];
        try {
            foreach (self::JOBS as $name => [$every, $hour]) {
                if (!self::due($name, $every, $hour, $now)) {
                    continue;
                }
                try {
                    $out[$name] = self::job($name);
                    Settings::set(["cron.job.$name" => date('Y-m-d H:i:s', $now), "cron.job.$name.error" => null]);
                } catch (Throwable $e) {
                    // Hata kaydedilir; iş bir sonraki saatte yeniden denenir (son çalışma güncellenmez)
                    $out[$name] = 'HATA: ' . $e->getMessage();
                    Settings::set(["cron.job.$name.error" => date('Y-m-d H:i', $now) . ' ' . mb_substr($e->getMessage(), 0, 250)]);
                }
            }
            Settings::set(['cron.last_run' => date('Y-m-d H:i:s', $now)]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $out;
    }

    /** Ayarlar → Sistem için: her işin son çalışması ve hatası */
    public static function status(): array
    {
        $rows = [];
        foreach (self::JOBS as $name => [$every, $hour]) {
            $rows[$name] = [
                'every' => $every,
                'hour'  => $hour,
                'last'  => Settings::get("cron.job.$name"),
                'error' => Settings::get("cron.job.$name.error"),
            ];
        }
        return $rows;
    }

    public static function healthy(): bool
    {
        $last = Settings::get('cron.last_run');
        return $last && strtotime($last) > time() - self::STALE_HOURS * 3600;
    }

    private static function due(string $name, string $every, int $hour, int $now): bool
    {
        if ((int) date('G', $now) < $hour) {
            return false;
        }
        $last = Settings::get("cron.job.$name");
        if (!$last) {
            return true;
        }
        return $every === 'hourly'
            ? date('Y-m-d H', strtotime($last)) !== date('Y-m-d H', $now)
            : substr($last, 0, 10) !== date('Y-m-d', $now);
    }

    private static function job(string $name): string
    {
        switch ($name) {
            case 'reminders':
                $r = ReminderService::run();
                return sprintf('%d kesin süre uyarısı, %d özet e-postası', $r['deadline'], $r['digest']);
            case 'transfers':
                $r = TransferService::cleanup();
                return sprintf('%d süresi dolan, %d yarım kalan silindi, %d hata', $r['expired'], $r['abandoned'], $r['failed']);
            case 'outlook':
                $r = OutlookService::processPending();
                return sprintf('%d kaydedildi, %d bekliyor, %d bulunamadı', $r['saved'], $r['waiting'], $r['failed']);
        }
        throw new \LogicException("Bilinmeyen iş: $name");
    }
}
