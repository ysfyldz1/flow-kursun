<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use RuntimeException;

/**
 * Microsoft 365 bağlantısı. Her kurum kendi Entra kuruluşunda tek kiracılı (single-tenant) bir uygulama kaydeder;
 * Tenant ID / Client ID / Secret ayarlardan gelir, koda yazılmaz.
 *  - Giriş: kullanıcı adına (delegated) OpenID Connect, yalnızca kurumun kendi kiracısı.
 *  - Uygulama belirteci (client credentials): sistem e-postası gibi kullanıcısız işler için.
 */
final class Microsoft
{
    private const LOGIN = 'https://login.microsoftonline.com';
    private const GRAPH = 'https://graph.microsoft.com/v1.0';
    private const SIGNIN_SCOPES = 'openid profile email User.Read';
    /** Kullanıcının kendi posta kutusu ve OneDrive'ı için (delegated); offline_access yenileme belirteci verir. */
    public const CONNECT_SCOPES = 'openid profile email offline_access User.Read Mail.Read Files.ReadWrite';

    public static function tenantId(): ?string
    {
        return Settings::get('ms.tenant_id') ?: null;
    }

    public static function clientId(): ?string
    {
        return Settings::get('ms.client_id') ?: null;
    }

    public static function isConfigured(): bool
    {
        return self::tenantId() && self::clientId() && Settings::get('ms.client_secret');
    }

    /** Microsoft ile girişte e-posta/OneDrive izinleri de istenip hesap bağlansın mı (Azure'da yönetici onayı verildikten sonra açılır). */
    public static function loginConnects(): bool
    {
        return Settings::get('ms.login_connect') === '1';
    }

    public static function loginEnabled(): bool
    {
        return self::isConfigured() && Settings::get('ms.login_enabled') === '1';
    }

    /** Entra'daki uygulama kaydına girilecek yönlendirme adresi. */
    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/microsoft/callback';
    }

    /** Giriş ya da ($connect) kullanıcı hesabını bağlama için yetkilendirme adresi. */
    public static function authorizeUrl(string $state, string $nonce, bool $connect = false, ?string $loginHint = null): string
    {
        return self::LOGIN . '/' . rawurlencode((string) self::tenantId()) . '/oauth2/v2.0/authorize?' . http_build_query(array_filter([
            'client_id'     => self::clientId(),
            'response_type' => 'code',
            'redirect_uri'  => self::redirectUri(),
            'response_mode' => 'query',
            'scope'         => $connect ? self::CONNECT_SCOPES : self::SIGNIN_SCOPES,
            'state'         => $state,
            'nonce'         => $nonce,
            'prompt'        => $loginHint ? null : 'select_account',
            'login_hint'    => $loginHint,
        ]));
    }

    /**
     * Yetki kodunu belirtece çevirir ve kimlik bilgilerini döndürür.
     * id_token doğrudan token uç noktasından TLS ile geldiği için imza yerine içerik doğrulanır (OIDC Core 3.1.3.7).
     *
     * $connect ile yenileme belirteci de döner ('token').
     *
     * @return array{oid: string, email: string, name: string, token?: array}
     */
    public static function exchangeCode(string $code, string $nonce, bool $connect = false): array
    {
        $token = self::tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => self::redirectUri(),
            'scope'        => $connect ? self::CONNECT_SCOPES : self::SIGNIN_SCOPES,
        ]);

        $parts = explode('.', (string) ($token['id_token'] ?? ''));
        $claims = count($parts) === 3 ? json_decode(self::b64url($parts[1]), true) : null;
        if (!is_array($claims)
            || ($claims['aud'] ?? null) !== self::clientId()
            || strcasecmp((string) ($claims['tid'] ?? ''), (string) self::tenantId()) !== 0
            || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
            || ($claims['exp'] ?? 0) < time() - 300
            || empty($claims['oid'])) {
            throw new RuntimeException('Invalid id_token');
        }

        return [
            'oid'   => (string) $claims['oid'],
            'email' => mb_strtolower((string) ($claims['email'] ?? $claims['preferred_username'] ?? '')),
            'name'  => (string) ($claims['name'] ?? ''),
        ] + ($connect ? ['token' => $token] : []);
    }

    // --- Kullanıcı adına (delegated) bağlantı: kişinin yalnız kendi posta kutusu ve OneDrive'ı ---

    /** Bağlantı bilgisi (ekranda göstermek için) ya da null. */
    public static function connection(int $userId): ?array
    {
        $row = DB::one("SELECT scopes, meta, created_at, updated_at FROM integration_tokens WHERE provider = 'microsoft' AND user_id = ?", [$userId]);
        if (!$row) {
            return null;
        }
        return ['scopes' => explode(' ', (string) $row['scopes']), 'connected_at' => $row['created_at']] + (json_decode((string) $row['meta'], true) ?: []);
    }

    /**
     * Bağlantıyı kaydeder. Bağlanan Microsoft hesabı Flow kullanıcısının kendisi olmalı
     * (kayıtlı oid ya da aynı e-posta); başkasının posta kutusu bağlanamaz.
     */
    public static function connectUser(array $user, array $ms): void
    {
        $sameOid = $user['ms_object_id'] && hash_equals((string) $user['ms_object_id'], $ms['oid']);
        $sameEmail = !$user['ms_object_id'] && $ms['email'] !== '' && strcasecmp($ms['email'], (string) $user['email']) === 0;
        if (!$sameOid && !$sameEmail) {
            throw new RuntimeException(__('ms.connect_mismatch', ['email' => $ms['email']]));
        }
        if (empty($ms['token']['refresh_token'])) {
            throw new RuntimeException('No refresh token (offline_access)');
        }
        $data = [
            'access_token'  => Secret::encrypt($ms['token']['access_token']),
            'refresh_token' => Secret::encrypt($ms['token']['refresh_token']),
            'expires_at'    => date('Y-m-d H:i:s', time() + (int) ($ms['token']['expires_in'] ?? 3600)),
            'scopes'        => (string) ($ms['token']['scope'] ?? ''),
            'meta'          => json_encode(['email' => $ms['email'], 'name' => $ms['name']], JSON_UNESCAPED_UNICODE),
        ];
        DB::transaction(function () use ($user, $ms, $data) {
            DB::update('users', ['ms_object_id' => $ms['oid'], 'ms_upn' => $ms['email'] ?: null], 'id = ?', [$user['id']]);
            DB::query("DELETE FROM integration_tokens WHERE provider = 'microsoft' AND user_id = ?", [$user['id']]);
            DB::insert('integration_tokens', $data + ['provider' => 'microsoft', 'user_id' => $user['id']]);
        });
        Audit::log('microsoft.connected', 'user', (int) $user['id'], ['scopes' => $data['scopes']]);
    }

    public static function disconnectUser(int $userId): void
    {
        DB::query("DELETE FROM integration_tokens WHERE provider = 'microsoft' AND user_id = ?", [$userId]);
        Audit::log('microsoft.disconnected', 'user', $userId);
    }

    /**
     * Kullanıcının erişim belirteci; süresi dolmuşsa yenileme belirteciyle yenilenir (yenisi de saklanır).
     * Yenileme reddedilirse (parola değişti, izin geri alındı) bağlantı silinir ve kod 401 ile istisna atılır.
     */
    public static function userToken(int $userId): string
    {
        $row = DB::one("SELECT * FROM integration_tokens WHERE provider = 'microsoft' AND user_id = ?", [$userId]);
        if (!$row) {
            throw new RuntimeException(__('ms.not_connected'), 401);
        }
        if ($row['expires_at'] > date('Y-m-d H:i:s', time() + 120) && ($plain = Secret::decrypt($row['access_token']))) {
            return $plain;
        }
        try {
            $token = self::tokenRequest([
                'grant_type'    => 'refresh_token',
                'refresh_token' => (string) Secret::decrypt($row['refresh_token']),
                'scope'         => self::CONNECT_SCOPES,
            ]);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'AADSTS')) {
                self::disconnectUser($userId);
                throw new RuntimeException(__('ms.reconnect_needed'), 401);
            }
            throw $e;
        }
        DB::update('integration_tokens', [
            'access_token'  => Secret::encrypt($token['access_token']),
            'refresh_token' => Secret::encrypt($token['refresh_token'] ?? (string) Secret::decrypt($row['refresh_token'])),
            'expires_at'    => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
            'scopes'        => (string) ($token['scope'] ?? $row['scopes']),
        ], 'id = ?', [$row['id']]);
        return $token['access_token'];
    }

    /** Kullanıcı adına Graph çağrısı (/me/...). Hata davranışı graph() ile aynı. */
    public static function graphAs(int $userId, string $method, string $path, ?array $body = null): array
    {
        [$status, $json] = self::http($method, self::GRAPH . $path, $body !== null ? json_encode($body) : null, [
            'Authorization: Bearer ' . self::userToken($userId),
            'Content-Type: application/json',
        ]);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException($json['error']['message'] ?? "Graph HTTP $status", $status);
        }
        return $json ?? [];
    }

    /** Uygulama belirteci; süresi dolana kadar integration_tokens'ta (user_id NULL) saklanır. */
    public static function appToken(): string
    {
        $row = DB::one("SELECT * FROM integration_tokens WHERE provider = 'microsoft' AND user_id IS NULL");
        if ($row && $row['expires_at'] > date('Y-m-d H:i:s', time() + 120)) {
            $plain = Secret::decrypt($row['access_token']);
            if ($plain) {
                return $plain;
            }
        }

        $token = self::tokenRequest([
            'grant_type' => 'client_credentials',
            'scope'      => 'https://graph.microsoft.com/.default',
        ]);
        $data = [
            'access_token' => Secret::encrypt($token['access_token']),
            'expires_at'   => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
        ];
        if ($row) {
            DB::update('integration_tokens', $data, 'id = ?', [$row['id']]);
        } else {
            DB::insert('integration_tokens', $data + ['provider' => 'microsoft', 'user_id' => null]);
        }
        return $token['access_token'];
    }

    /** Uygulama belirteciyle Graph çağrısı. 2xx dışı yanıtta Graph'ın hata mesajıyla, kod = HTTP durumu olan istisna atar. */
    public static function graph(string $method, string $path, ?array $body = null): array
    {
        [$status, $json] = self::http($method, self::GRAPH . $path, $body !== null ? json_encode($body) : null, [
            'Authorization: Bearer ' . self::appToken(),
            'Content-Type: application/json',
        ]);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException($json['error']['message'] ?? "Graph HTTP $status", $status);
        }
        return $json ?? [];
    }

    /** Yeni müşteride eski bağlantıdan iz kalmasın: ayarlar, belirteçler ve kullanıcı eşleşmeleri silinir. */
    public static function reset(): void
    {
        DB::transaction(function () {
            DB::query("DELETE FROM settings WHERE `key` LIKE 'ms.%' OR `key` LIKE 'docs.%'");
            DB::query('UPDATE projects SET drive_folder_id = NULL');
            DB::query('DELETE FROM document_shares');
            DB::query("DELETE FROM integration_tokens WHERE provider = 'microsoft'");
            DB::query('UPDATE users SET ms_object_id = NULL, ms_upn = NULL');
            if (Settings::get('mail.driver') === 'graph') {
                DB::query("DELETE FROM settings WHERE `key` = 'mail.driver'");
            }
        });
        Settings::set([]);
    }

    private static function tokenRequest(array $params): array
    {
        $secret = Secret::decrypt(Settings::get('ms.client_secret'));
        if (!self::isConfigured() || !$secret) {
            throw new RuntimeException('Microsoft 365 is not configured');
        }
        [$status, $json] = self::http(
            'POST',
            self::LOGIN . '/' . rawurlencode((string) self::tenantId()) . '/oauth2/v2.0/token',
            http_build_query($params + ['client_id' => self::clientId(), 'client_secret' => $secret]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        if ($status !== 200 || empty($json['access_token'])) {
            // AADSTS kodu kurulumdaki hatayı bulmayı kolaylaştırır (ör. AADSTS7000215: yanlış secret)
            throw new RuntimeException(strtok((string) ($json['error_description'] ?? "HTTP $status"), "\r\n"));
        }
        return $json;
    }

    /** @return array{0: int, 1: ?array} */
    private static function http(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, json_decode($response, true)];
    }

    private static function b64url(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
