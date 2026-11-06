<?php

declare(strict_types=1);

namespace App\Services;

/**
 * PWA ve tarayıcı ikonları. Tek kare görselden tüm boyutlar GD ile üretilir;
 * görsel yoksa kurum renginde, kurum adının baş harfiyle varsayılan ikon üretilir.
 */
final class AppIcon
{
    /** ad => [boyut, maskable mı] */
    public const SIZES = [
        'icon-192.png'     => [192, false],
        'icon-512.png'     => [512, false],
        'maskable-512.png' => [512, true],
        'apple-180.png'    => [180, true],     // iOS kenarları kendisi yuvarlar; dolu zemin ister
        'favicon-32.png'   => [32, false],
    ];

    private static function dir(): string
    {
        return BASE_PATH . '/storage/uploads/icons';
    }

    /** Yüklenen görselden ikonları üretir. Hata varsa mesaj döner. */
    public static function fromUpload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return __('org.icon_invalid');
        }
        $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$src) {
            return __('org.icon_invalid');
        }
        if (min(imagesx($src), imagesy($src)) < 192) {
            return __('org.icon_too_small');
        }
        self::writeAll(self::squareCrop($src));
        return null;
    }

    /** Yüklü ikonu siler; bir sonraki istekte varsayılan ikon üretilir. */
    public static function reset(): void
    {
        foreach (glob(self::dir() . '/*.png') ?: [] as $f) {
            unlink($f);
        }
        Settings::set(['org.icon_custom' => '0', 'org.icon_version' => (string) time()]);
    }

    /** İstenen ikonun yolu; yoksa varsayılanı üretir. */
    public static function path(string $name): ?string
    {
        if (!isset(self::SIZES[$name])) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        if (!is_file($path)) {
            self::writeAll(self::defaultImage());
        }
        return is_file($path) ? $path : null;
    }

    public static function url(string $name): string
    {
        return '/icons/' . $name . '?v=' . Settings::get('org.icon_version', '1');
    }

    private static function writeAll(\GdImage $square): void
    {
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        [$r, $g, $b] = self::rgb((string) Settings::get('org.primary_color', '#1e3a8a'));
        foreach (self::SIZES as $name => [$size, $maskable]) {
            $img = imagecreatetruecolor($size, $size);
            if ($maskable) {
                // Güvenli alan: ikon %80'e küçültülüp kurum renginde zemine ortalanır
                imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
                $inner = (int) round($size * 0.8);
                $off = (int) round(($size - $inner) / 2);
                imagecopyresampled($img, $square, $off, $off, 0, 0, $inner, $inner, imagesx($square), imagesy($square));
            } else {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
                imagealphablending($img, true);
                imagecopyresampled($img, $square, 0, 0, 0, 0, $size, $size, imagesx($square), imagesy($square));
            }
            imagepng($img, self::dir() . '/' . $name);
        }
        Settings::set(['org.icon_version' => (string) time()]);
    }

    private static function squareCrop(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor($side, $side);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopy($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $side, $side);
        return $dst;
    }

    /** Kurum renginde yuvarlak köşeli kare + baş harf */
    private static function defaultImage(): \GdImage
    {
        $size = 512;
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        [$r, $g, $b] = self::rgb((string) Settings::get('org.primary_color', '#1e3a8a'));
        $bg = imagecolorallocate($img, $r, $g, $b);
        $rad = 96;
        imagefilledrectangle($img, $rad, 0, $size - $rad, $size, $bg);
        imagefilledrectangle($img, 0, $rad, $size, $size - $rad, $bg);
        foreach ([[$rad, $rad], [$size - $rad, $rad], [$rad, $size - $rad], [$size - $rad, $size - $rad]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $rad * 2, $rad * 2, $bg);
        }

        $letter = mb_strtoupper(mb_substr((string) Settings::get('org.name', 'F'), 0, 1));
        $font = BASE_PATH . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
        $white = imagecolorallocate($img, 255, 255, 255);
        if (is_file($font)) {
            $box = imagettfbbox(260, 0, $font, $letter);
            $tw = $box[2] - $box[0];
            $th = $box[1] - $box[7];
            imagettftext($img, 260, 0, (int) (($size - $tw) / 2 - $box[0]), (int) (($size + $th) / 2 - $box[1]), $white, $font, $letter);
        }
        return $img;
    }

    private static function rgb(string $hex): array
    {
        return preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)
            ? [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])]
            : [30, 58, 138];
    }
}
