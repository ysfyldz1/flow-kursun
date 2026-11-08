<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DB'deki gizli değerler (client secret, SMTP şifresi) bu kuruluma özel anahtarla şifrelenir.
 * Anahtar storage/app.key'de durur, git'e girmez; DB dökümü tek başına sırrı açığa çıkarmaz.
 */
final class Secret
{
    private const PREFIX = 'gcm:';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    private static function key(): string
    {
        $file = BASE_PATH . '/storage/app.key';
        if (!is_file($file)) {
            file_put_contents($file, base64_encode(random_bytes(32)));
        }
        return base64_decode(trim((string) file_get_contents($file)));
    }

    /** AES-256-GCM (OpenSSL): sodium eklentisi her hostingde bulunmuyor. */
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $cipher): ?string
    {
        if ($cipher && str_starts_with($cipher, self::PREFIX)) {
            $raw = base64_decode(substr($cipher, strlen(self::PREFIX)), true);
            if ($raw === false || strlen($raw) <= self::IV_BYTES + self::TAG_BYTES) {
                return null;
            }
            $plain = openssl_decrypt(
                substr($raw, self::IV_BYTES + self::TAG_BYTES),
                'aes-256-gcm',
                self::key(),
                OPENSSL_RAW_DATA,
                substr($raw, 0, self::IV_BYTES),
                substr($raw, self::IV_BYTES, self::TAG_BYTES)
            );
            return $plain === false ? null : $plain;
        }
        return self::decryptLegacy($cipher);
    }

    /** Önek taşımayan eski kayıtlar sodium ile şifrelenmişti. */
    private static function decryptLegacy(?string $cipher): ?string
    {
        if (!function_exists('sodium_crypto_secretbox_open')) {
            return null;
        }
        $raw = $cipher ? base64_decode($cipher, true) : false;
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        return $plain === false ? null : $plain;
    }
}
