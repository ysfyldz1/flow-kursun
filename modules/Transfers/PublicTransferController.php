<?php

declare(strict_types=1);

namespace Modules\Transfers;

use App\Core\Lang;
use App\Core\View;
use App\Services\Settings;
use App\Services\TransferService;
use Throwable;

/** Alıcının oturum açmadan dosya indirdiği sayfa (/t/{token}). */
final class PublicTransferController
{
    public function show(string $token): void
    {
        $t = $this->load($token);
        $this->render($token, $t, $this->state($t));
    }

    public function unlock(string $token): void
    {
        $t = $this->load($token);
        if ($this->state($t) === 'locked') {
            if (password_verify((string) ($_POST['password'] ?? ''), $t['password_hash'])) {
                $_SESSION['transfer_unlocked'][$t['id']] = true;
                redirect("/t/$token");
            }
            sleep(1);   // deneme-yanılmayı yavaşlatır
            $this->render($token, $t, 'locked', __('transfers.password_wrong'));
            return;
        }
        redirect("/t/$token");
    }

    public function file(string $token, string $id): void
    {
        $t = $this->load($token);
        if ($this->state($t) !== 'ok') {
            redirect("/t/$token");
        }
        $file = TransferService::file($t, (int) $id) ?? abort(404);
        try {
            $url = TransferService::downloadUrl($t, (string) $file['item_id']);
        } catch (Throwable $e) {
            error_log('Transfer indirme: ' . $e->getMessage());
            http_response_code(503);
            $this->render($token, $t, 'unavailable');
            return;
        }
        TransferService::recordDownload($t, $file);
        header('Location: ' . $url);
        exit;
    }

    public function all(string $token): void
    {
        $t = $this->load($token);
        if ($this->state($t) !== 'ok') {
            redirect("/t/$token");
        }
        $files = TransferService::files((int) $t['id']);
        // Akış başladıktan sonra hata sayfası gösterilemez; bağlantı ilk dosyayla önceden doğrulanır
        try {
            $firstUrl = TransferService::downloadUrl($t, (string) $files[0]['item_id']);
        } catch (Throwable $e) {
            error_log('Transfer ZIP: ' . $e->getMessage());
            http_response_code(503);
            $this->render($token, $t, 'unavailable');
            return;
        }
        TransferService::recordDownload($t, null);
        session_write_close();   // uzun indirmede oturum kilitli kalmasın
        TransferService::streamZip($t, $files, $firstUrl);
    }

    private function load(string $token): ?array
    {
        $t = TransferService::findByToken($token);
        $lang = is_string($_GET['lang'] ?? null) ? $_GET['lang'] : ($t['locale'] ?? (string) Settings::get('org.locale', 'tr'));
        Lang::set($lang);
        return $t;
    }

    private function state(?array $t): string
    {
        return match (true) {
            !$t                                                                            => 'notfound',
            TransferService::isExpired($t) || (bool) $t['files_deleted_at']                => 'expired',
            $t['password_hash'] && empty($_SESSION['transfer_unlocked'][$t['id']])         => 'locked',
            default                                                                        => 'ok',
        };
    }

    private function render(string $token, ?array $t, string $state, ?string $error = null): void
    {
        echo View::partial('Transfers::public', [
            'state' => $state,
            't'     => $t,
            'token' => $token,
            'error' => $error,
            'files' => $state === 'ok' || $state === 'unavailable' ? TransferService::files((int) $t['id']) : [],
        ]);
    }
}
