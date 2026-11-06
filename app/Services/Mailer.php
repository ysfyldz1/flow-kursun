<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Lang;
use App\Core\View;
use RuntimeException;
use Throwable;

/**
 * Sistem e-postaları. Yol ayarlardan seçilir:
 *  - graph: kurumun (ortak) posta kutusundan Microsoft Graph ile (Mail.Send uygulama izni)
 *  - smtp:  herhangi bir SMTP sunucusu (geliştirmede Mailpit: 127.0.0.1:1025)
 *  - log:   gönderilmez, storage/logs/mail.log'a yazılır (varsayılan)
 */
final class Mailer
{
    public const DRIVERS = ['log', 'graph', 'smtp'];

    public static function driver(): string
    {
        $driver = (string) Settings::get('mail.driver', 'log');
        return in_array($driver, self::DRIVERS, true) ? $driver : 'log';
    }

    /** Hata durumunda false döner; ayrıntı mail_log'dadır. $throw ile hata çağırana iletilir (test gönderimi). */
    public static function send(string $to, string $subject, string $html, bool $throw = false): bool
    {
        $driver = self::driver();
        try {
            match ($driver) {
                'graph' => self::viaGraph($to, $subject, $html),
                'smtp'  => self::viaSmtp($to, $subject, $html),
                default => self::viaLog($to, $subject, $html),
            };
            self::log($driver, $to, $subject, null);
            return true;
        } catch (Throwable $e) {
            self::log($driver, $to, $subject, $e->getMessage());
            if ($throw) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * views/mail/<view>.php şablonunu alıcının dilinde işleyip gönderir; arayüz dili sonra geri alınır.
     * Konu, lang dosyasındaki "mail.<view>_subject" anahtarından gelir.
     */
    public static function sendView(string $to, string $locale, string $view, array $data = []): bool
    {
        $previous = Lang::locale();
        Lang::set($locale);
        try {
            $data += ['org' => (string) Settings::get('org.name', config('app.name'))];
            $subject = __("mail.{$view}_subject", $data);
            $html = View::partial('mail/layout', ['content' => View::partial("mail/$view", $data)]);
        } finally {
            Lang::set($previous);
        }
        return self::send($to, $subject, $html);
    }

    /** Gerçekten dışarı giden bir yol seçili mi (günlüğe yazma değil). */
    public static function delivers(): bool
    {
        return self::driver() !== 'log';
    }

    private static function viaGraph(string $to, string $subject, string $html): void
    {
        $from = (string) Settings::get('mail.from_address');
        if ($from === '') {
            throw new RuntimeException(__('mail.no_from'));
        }
        Microsoft::graph('POST', '/users/' . rawurlencode($from) . '/sendMail', [
            'message' => [
                'subject'      => $subject,
                'body'         => ['contentType' => 'HTML', 'content' => $html],
                'toRecipients' => [['emailAddress' => ['address' => $to]]],
            ],
            'saveToSentItems' => false,
        ]);
    }

    private static function viaLog(string $to, string $subject, string $html): void
    {
        $line = sprintf("[%s] To: %s | %s\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $html);
        file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND);
    }

    /** Bağımlılıksız küçük SMTP istemcisi: STARTTLS / SSL ve AUTH LOGIN destekler. */
    private static function viaSmtp(string $to, string $subject, string $html): void
    {
        $host = (string) Settings::get('mail.smtp_host');
        $port = (int) Settings::get('mail.smtp_port', 587);
        $secure = (string) Settings::get('mail.smtp_secure', 'tls');     // tls, ssl, none
        $user = (string) Settings::get('mail.smtp_user');
        $pass = Secret::decrypt(Settings::get('mail.smtp_pass')) ?? '';
        $from = (string) Settings::get('mail.from_address');
        if ($host === '' || $from === '') {
            throw new RuntimeException(__('mail.no_from'));
        }

        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15);
        if (!$fp) {
            throw new RuntimeException("SMTP: $errstr ($errno)");
        }
        stream_set_timeout($fp, 15);

        $cmd = function (?string $line, int $expect) use ($fp): void {
            if ($line !== null) {
                fwrite($fp, $line . "\r\n");
            }
            $reply = '';
            while (($l = fgets($fp, 515)) !== false) {
                $reply .= $l;
                if (($l[3] ?? '') === ' ') {
                    break;
                }
            }
            if ((int) substr($reply, 0, 3) !== $expect) {
                throw new RuntimeException('SMTP: ' . trim($reply ?: 'no response'));
            }
        };

        try {
            $cmd(null, 220);
            $cmd('EHLO flow', 250);
            if ($secure === 'tls') {
                $cmd('STARTTLS', 220);
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $cmd('EHLO flow', 250);
            }
            if ($user !== '') {
                $cmd('AUTH LOGIN', 334);
                $cmd(base64_encode($user), 334);
                $cmd(base64_encode($pass), 235);
            }
            $cmd("MAIL FROM:<$from>", 250);
            $cmd("RCPT TO:<$to>", 250);
            $cmd('DATA', 354);

            $fromName = (string) Settings::get('org.name', config('app.name'));
            $headers = [
                'Date: ' . date('r'),
                'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>",
                "To: <$to>",
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $cmd(implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html)) . '.', 250);
            $cmd('QUIT', 221);
        } finally {
            fclose($fp);
        }
    }

    private static function log(string $driver, string $to, string $subject, ?string $error): void
    {
        DB::insert('mail_log', [
            'driver'    => $driver,
            'recipient' => $to,
            'subject'   => mb_substr($subject, 0, 255),
            'status'    => $error === null ? 'sent' : 'failed',
            'error'     => $error,
        ]);
    }
}
