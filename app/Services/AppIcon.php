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
        if (max(imagesx($src), imagesy($src)) < 192) {
            return __('org.icon_too_small');
        }
        imagepalettetotruecolor($src);
        $square = self::squareFit($src);
        self::writeAll($square, self::maskableBackground($square));
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

    /** @param array{int,int,int}|null $background maskable zemini; verilmezse kurum rengi */
    private static function writeAll(\GdImage $square, ?array $background = null): void
    {
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0775, true);
        }
        [$r, $g, $b] = $background ?? self::rgb((string) Settings::get('org.primary_color', '#1e3a8a'));
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

    /** Görseli kırpmadan kareye ortalar; boşluklar görselin kendi zemin rengiyle ya da şeffaf doldurulur */
    private static function squareFit(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = max($w, $h);
        $dst = imagecreatetruecolor($side, $side);
        imagesavealpha($dst, true);
        imagealphablending($dst, false);
        $corner = imagecolorsforindex($src, imagecolorat($src, 0, 0));
        $opaque = $corner['alpha'] < 64;
        imagefill($dst, 0, 0, $opaque
            ? imagecolorallocate($dst, $corner['red'], $corner['green'], $corner['blue'])
            : imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopy($dst, $src, (int) (($side - $w) / 2), (int) (($side - $h) / 2), 0, 0, $w, $h);
        return $dst;
    }

    /** Kendi zemini olan görselde o renk; şeffafta kurum rengi, görsel onunla karışıyorsa beyaz */
    private static function maskableBackground(\GdImage $square): array
    {
        $side = imagesx($square);
        $corner = imagecolorsforindex($square, imagecolorat($square, 0, 0));
        if ($corner['alpha'] < 64) {
            return [$corner['red'], $corner['green'], $corner['blue']];
        }

        $sum = [0, 0, 0];
        $count = 0;
        $step = max(1, intdiv($side, 64));
        for ($y = 0; $y < $side; $y += $step) {
            for ($x = 0; $x < $side; $x += $step) {
                $c = imagecolorsforindex($square, imagecolorat($square, $x, $y));
                if ($c['alpha'] < 64) {
                    $sum[0] += $c['red'];
                    $sum[1] += $c['green'];
                    $sum[2] += $c['blue'];
                    $count++;
                }
            }
        }
        $primary = self::rgb((string) Settings::get('org.primary_color', '#1e3a8a'));
        if (!$count) {
            return $primary;
        }
        $average = array_map(fn (int $v) => (int) round($v / $count), $sum);
        return self::contrast($average, $primary) < 3 ? [255, 255, 255] : $primary;
    }

    private static function contrast(array $a, array $b): float
    {
        $lum = function (array $rgb): float {
            [$r, $g, $b] = array_map(function (int $v): float {
                $s = $v / 255;
                return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
            }, $rgb);
            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };
        $la = $lum($a);
        $lb = $lum($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
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
