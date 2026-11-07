<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Word (.docx) şablonlarında {ALAN} yer tutucularını doldurur. Word bir yer tutucuyu birden fazla
 * run'a (<w:t>) bölebildiği için eşleştirme paragrafın birleşik metni üzerinde yapılır; değer ilk parçanın biçimini alır.
 */
final class DocxTemplate
{
    /** Şablonu $values ile doldurup $dest'e yazar; $values'ta olmayan alanlar boş kalır. */
    public static function fill(string $src, string $dest, array $values): bool
    {
        if (!copy($src, $dest)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($dest) !== true) {
            return false;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $name)) {
                continue;
            }
            $xml = self::replace($zip->getFromIndex($i), '/\{[A-Z_]+\}/', fn ($token) => $values[trim($token, '{}')] ?? '');
            $zip->addFromString($name, $xml);
        }
        return $zip->close();
    }

    /** $pattern ile eşleşen her parçayı $callback'in döndürdüğü düz metinle değiştirir (XML kaçışı burada yapılır). */
    private static function replace(string $xml, string $pattern, callable $callback): string
    {
        return preg_replace_callback('#<w:p[ >].*?</w:p>#s', function ($p) use ($pattern, $callback) {
            $para = $p[0];
            if (!preg_match_all('#(<w:t(?: [^>]*)?>)([^<]*)(</w:t>)#', $para, $m, PREG_OFFSET_CAPTURE)) {
                return $para;
            }
            $texts = array_map(fn ($t) => html_entity_decode($t[0], ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[2]);
            if (!preg_match_all($pattern, implode('', $texts), $hits, PREG_OFFSET_CAPTURE)) {
                return $para;
            }

            // Bayt konumunu (düğüm, düğüm içi konum) çiftine çevirir
            $locate = function (int $pos) use ($texts): array {
                foreach ($texts as $i => $t) {
                    if ($pos < strlen($t) || $i === count($texts) - 1) {
                        return [$i, $pos];
                    }
                    $pos -= strlen($t);
                }
                return [0, 0];
            };

            // Değerler soldan sağa üretilir, yerleştirme önceki konumlar bozulmasın diye sağdan sola
            $values = array_map(fn ($hit) => (string) $callback($hit[0]), $hits[0]);
            foreach (array_reverse($hits[0], true) as $k => [$token, $start]) {
                [$si, $so] = $locate($start);
                [$ei, $eo] = $locate($start + strlen($token) - 1);
                if ($si === $ei) {
                    $texts[$si] = substr($texts[$si], 0, $so) . $values[$k] . substr($texts[$si], $eo + 1);
                } else {
                    $texts[$si] = substr($texts[$si], 0, $so) . $values[$k];
                    for ($i = $si + 1; $i < $ei; $i++) {
                        $texts[$i] = '';
                    }
                    $texts[$ei] = substr($texts[$ei], $eo + 1);
                }
            }

            for ($i = count($m[0]) - 1; $i >= 0; $i--) {
                $node = '<w:t xml:space="preserve">' . htmlspecialchars($texts[$i], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t>';
                $para = substr_replace($para, $node, $m[0][$i][1], strlen($m[0][$i][0]));
            }
            return $para;
        }, $xml);
    }
}
