<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DB'deki gizli değerler (client secret, SMTP şifresi) bu kuruluma özel anahtarla şifrelenir.
 * Anahtar storage/app.key'de durur, git'e girmez; DB dökümü tek başına sırrı açığa çıkarmaz.
 */
final class Secret
{
    private static function key(): string
    {
        $file = BASE_PATH . '/storage/app.key';
        if (!is_file($file)) {
            file_put_contents($file, base64_encode(sodium_crypto_secretbox_keygen()));
        }
        return base64_decode(trim((string) file_get_contents($file)));
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $cipher): ?string
    {
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
