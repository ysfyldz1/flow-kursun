<?php

declare(strict_types=1);

namespace Modules\Outlook;

use App\Core\Auth;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\AppIcon;
use App\Services\Microsoft;
use App\Services\OutlookService;
use App\Services\Settings;
use RuntimeException;
use Throwable;

/**
 * Outlook eklentisi: panel sayfası, giriş penceresi, panelin konuştuğu JSON API ve kuruma özel manifest.
 * İş mantığı OutlookService'te.
 */
final class OutlookController
{
    /** Outlook'un sağında açılan panel (yerleşimsiz, office.js ile). */
    public function pane(): void
    {
        header('Cache-Control: no-store');
        View::render('Outlook::pane', [
            'org'      => (string) Settings::get('org.name', config('app.name')),
            'decimal'  => \App\Services\TimeService::decimal(),
        ], null);
    }

    /**
     * Panelin açtığı giriş penceresi (Office dialog, tam sayfa olduğu için çerezler çalışır).
     * Kişi Flow'a girer; Microsoft hesabı bağlı değilse bağlatılır; sonra panele anahtar gönderilir.
     */
    public function connect(): void
    {
        if (Microsoft::isConfigured() && !Microsoft::connection((int) Auth::id())) {
            $_SESSION['_intended'] = '/outlook/connect';
            redirect('/account/microsoft/connect');
        }
        header('Cache-Control: no-store');
        View::render('Outlook::connect', [
            'token' => Microsoft::connection((int) Auth::id()) ? OutlookService::issueToken((int) Auth::id()) : null,
            'name'  => auth()['name'],
        ], null);
    }

    public function me(): void
    {
        $user = $this->user();
        json_response([
            'name'      => $user['name'],
            'email'     => $user['email'],
            'connected' => (bool) Microsoft::connection((int) $user['id']),
            'canTime'   => can('time.own') || can('time.all'),
        ]);
    }

    public function context(): void
    {
        $this->user();
        json_response(OutlookService::context($this->body()));
    }

    public function projects(): void
    {
        $this->user();
        json_response(['projects' => OutlookService::search((string) ($_GET['q'] ?? ''))]);
    }

    public function save(): void
    {
        $this->user();
        $this->run(fn () => OutlookService::save($this->body()));
    }

    public function queue(): void
    {
        $this->user();
        $this->run(function () {
            OutlookService::queue($this->body());
            return ['queued' => true];
        });
    }

    public function unqueue(): void
    {
        $this->user();
        OutlookService::unqueue((string) ($this->body()['marker'] ?? ''));
        json_response(['ok' => true]);
    }

    public function logout(): void
    {
        $this->user();
        OutlookService::revokeToken($this->bearer());
        json_response(['ok' => true]);
    }

    /** Görev çubuğu simgeleri (manifest bunları ister): uygulama simgesinden küçültülür. */
    public function icon(string $size): void
    {
        $size = (int) $size;
        if (!in_array($size, [16, 32, 64, 80, 128], true) || !($src = AppIcon::path('icon-192.png'))) {
            abort(404);
        }
        $from = imagecreatefrompng($src);
        $to = imagecreatetruecolor($size, $size);
        imagealphablending($to, false);
        imagesavealpha($to, true);
        imagecopyresampled($to, $from, 0, 0, 0, 0, $size, $size, imagesx($from), imagesy($from));
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        imagepng($to);
        exit;
    }

    /**
     * Kuruma özel manifest: adresler bu kurulumun alan adından, kimlik kurulumda bir kez üretilir.
     * Microsoft 365 yönetim merkezi → Entegre uygulamalar'a yüklenir.
     */
    public function manifest(): void
    {
        $id = Settings::get('outlook.addin_id');
        if (!$id) {
            $b = random_bytes(16);
            $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
            $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
            $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
            Settings::set(['outlook.addin_id' => $id]);
        }
        $xml = View::partial('Outlook::manifest', [
            'id'      => $id,
            'base'    => rtrim((string) config('app.url'), '/'),
            'org'     => (string) Settings::get('org.name', config('app.name')),
            'version' => trim((string) @file_get_contents(BASE_PATH . '/VERSION')) ?: '1.0.0',
        ]);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="flow-outlook-manifest.xml"');
        echo $xml;
        exit;
    }

    // --- Yardımcılar ----------------------------------------------------------------------------------

    private function bearer(): string
    {
        // Bazı hostinglerde Apache Authorization başlığını PHP'ye geçirmez; panel kendi başlığını da gönderir
        if (!empty($_SERVER['HTTP_X_FLOW_TOKEN'])) {
            return (string) $_SERVER['HTTP_X_FLOW_TOKEN'];
        }
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : '';
    }

    /** Anahtar geçersizse 401: panel giriş düğmesini gösterir. */
    private function user(): array
    {
        $user = OutlookService::userFromToken($this->bearer());
        if (!$user) {
            json_response(['error' => 'unauthorized'], 401);
        }
        Auth::useForRequest($user);
        \App\Core\Lang::set($user['locale'] ?: config('app.locale'));
        return $user;
    }

    private function body(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    private function run(callable $fn): never
    {
        try {
            json_response(['ok' => true] + $fn());
        } catch (ValidationException $e) {
            json_response(['error' => 'validation', 'errors' => $e->errors], 422);
        } catch (RuntimeException $e) {
            $code = in_array($e->getCode(), [401, 403, 404], true) ? $e->getCode() : 502;
            json_response(['error' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            error_log('Outlook add-in: ' . $e->getMessage());
            json_response(['error' => $e->getMessage()], 500);
        }
    }
}
