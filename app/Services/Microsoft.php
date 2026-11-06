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

    public static function loginEnabled(): bool
    {
        return self::isConfigured() && Settings::get('ms.login_enabled') === '1';
    }

    /** Entra'daki uygulama kaydına girilecek yönlendirme adresi. */
    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/microsoft/callback';
    }

    public static function authorizeUrl(string $state, string $nonce): string
    {
        return self::LOGIN . '/' . rawurlencode((string) self::tenantId()) . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id'     => self::clientId(),
            'response_type' => 'code',
            'redirect_uri'  => self::redirectUri(),
            'response_mode' => 'query',
            'scope'         => self::SIGNIN_SCOPES,
            'state'         => $state,
            'nonce'         => $nonce,
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * Yetki kodunu belirtece çevirir ve kimlik bilgilerini döndürür.
     * id_token doğrudan token uç noktasından TLS ile geldiği için imza yerine içerik doğrulanır (OIDC Core 3.1.3.7).
     *
     * @return array{oid: string, email: string, name: string}
     */
    public static function exchangeCode(string $code, string $nonce): array
    {
        $token = self::tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => self::redirectUri(),
            'scope'        => self::SIGNIN_SCOPES,
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
        ];
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
