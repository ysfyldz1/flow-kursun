<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;

/**
 * Kurşun: teklif belgesindeki "İşin kapsamı ve ücret" tablosundan aşamaları okur.
 * Beklenen tablo: 1. sütun "Aşama N (Opsiyonel)" + aşama adı, 2. sütun madde madde kapsam, son sütun fiyat.
 * Sonuç kaydedilmez; düzenlenebilir önizlemeye gider.
 *
 * @return list<array{name: string, scope: string, fee: ?float, is_optional: bool}>
 */
final class PhaseImportService
{
    private const MARKER = '/^\s*(?:aşama|asama|phase|stage)\s*\d+\s*(?:\((?:opsiyonel|optional)\))?\s*/iu';
    private const OPTIONAL = '/\((?:opsiyonel|optional)\)|\bopsiyonel\b|\boptional\b/iu';

    public static function parse(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new ValidationException(['import' => __('phases.import_no_file')]);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        return self::parsePath($file['tmp_name'], $ext);
    }

    /** Test ve CLI için: dosya yolundan okur. */
    public static function parsePath(string $path, string $ext): array
    {
        $rows = match ($ext) {
            'docx'  => self::fromDocx($path),
            'pdf'   => self::fromPdf($path),
            default => throw new ValidationException(['import' => __('phases.import_bad_type')]),
        };
        $rows = array_values(array_filter($rows, fn ($r) => $r['name'] !== ''));
        if (!$rows) {
            throw new ValidationException(['import' => __('phases.import_nothing')]);
        }
        return $rows;
    }

    // --- Word ---

    private static function fromDocx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new ValidationException(['import' => __('phases.import_no_zip')]);
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            throw new ValidationException(['import' => __('phases.import_unreadable')]);
        }
        $zip->close();

        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Her tablo satırı: hücreler, hücre başına paragraf listesi (iç içe tablolar ayrı sayılmaz)
        $tables = [];
        foreach ($xp->query('//w:tbl[not(ancestor::w:tbl)]') as $tbl) {
            $rows = [];
            foreach ($xp->query('./w:tr', $tbl) as $tr) {
                $cells = [];
                foreach ($xp->query('./w:tc', $tr) as $tc) {
                    $paras = [];
                    foreach ($xp->query('.//w:p', $tc) as $p) {
                        $paras[] = self::paragraphText($xp, $p);
                    }
                    $cells[] = array_values(array_filter(array_map('trim', $paras), fn ($t) => $t !== ''));
                }
                $rows[] = $cells;
            }
            $tables[] = $rows;
        }

        // Önce "Aşama N" ile başlayan satırlar; yoksa en büyük tablonun başlık dışı satırları
        $out = [];
        foreach ($tables as $rows) {
            foreach ($rows as $cells) {
                if (count($cells) >= 2 && $cells[0] && preg_match(self::MARKER, $cells[0][0])) {
                    $out[] = self::fromCells($cells);
                }
            }
        }
        if (!$out && $tables) {
            usort($tables, fn ($a, $b) => count($b) <=> count($a));
            foreach (array_slice($tables[0], 1) as $cells) {
                if (count($cells) >= 2 && $cells[0]) {
                    $out[] = self::fromCells($cells);
                }
            }
        }
        return $out;
    }

    private static function paragraphText(\DOMXPath $xp, \DOMNode $p): string
    {
        $text = '';
        foreach ($xp->query('.//w:t | .//w:tab | .//w:br', $p) as $n) {
            $text .= match ($n->localName) {
                't'     => $n->textContent,
                'tab'   => ' ',
                default => "\n",
            };
        }
        return $text;
    }

    /** Hücrelerden aşama: ilk hücre ad (+ işaret), ikinci hücre kapsam, son hücre fiyat. */
    private static function fromCells(array $cells): array
    {
        $first = implode("\n", $cells[0]);
        $optional = (bool) preg_match(self::OPTIONAL, $first);
        $name = trim(preg_replace('/\s+/u', ' ', preg_replace(self::MARKER, '', $first)));
        $scope = count($cells) >= 3 ? $cells[1] : array_slice($cells[1], 0);
        $priceCell = count($cells) >= 3 ? implode(' ', end($cells)) : '';
        return [
            'name'        => $name,
            'scope'       => implode("\n", array_map([self::class, 'cleanItem'], $scope)),
            'fee'         => self::money($priceCell),
            'is_optional' => $optional,
        ];
    }

    // --- PDF (yalnız metin katmanı olan PDF) ---

    private static function fromPdf(string $path): array
    {
        try {
            $pdf = (new \Smalot\PdfParser\Parser())->parseFile($path);
        } catch (\Throwable) {
            throw new ValidationException(['import' => __('phases.import_unreadable')]);
        }
        $pages = $pdf->getPages();
        $text = '';
        foreach ($pages as $page) {
            $text .= $page->getText() . "\n";
        }
        if (trim($text) === '') {
            // "Yazdır → PDF" ile üretilmiş dosyalarda metin yoktur
            throw new ValidationException(['import' => __('phases.import_pdf_no_text')]);
        }

        // "Aşama N" işaretlerine göre parçala: ilk satır ad, para gibi görünen ilk ifade fiyat, kalanı kapsam
        $parts = preg_split('/(?=(?:^|\n)\s*(?:Aşama|Asama|Phase|Stage)\s*\d+)/u', $text);
        $out = [];
        foreach ($parts as $part) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $part)), fn ($l) => $l !== ''));
            if (!$lines || !preg_match(self::MARKER, $lines[0])) {
                continue;
            }
            $optional = (bool) preg_match(self::OPTIONAL, $lines[0]);
            $head = trim(preg_replace(self::MARKER, '', array_shift($lines)));
            $name = $head !== '' ? $head : (string) array_shift($lines);
            $fee = null;
            $scope = [];
            foreach ($lines as $l) {
                if ($fee === null && ($m = self::money($l)) !== null && mb_strlen($l) < 40) {
                    $fee = $m;
                    continue;
                }
                // Para birimi ya da şablon yer tutucusu kalıntıları atlanır
                if (preg_match('/^[\[\]●•\s]*(?:para\s*birimi|currency|usd|eur|try|tl|gbp|\$|€)?[\[\]●•\s]*$/iu', $l)
                    || preg_match('/^\[?●?\]?\s*\[?(?:para|PARA)$/u', $l) || preg_match('/^(?:birimi|Birimi|BİRİMİ)\]?$/u', $l)) {
                    continue;
                }
                $item = self::cleanItem($l);
                $bullet = $item !== $l;
                // PDF satır kaydırmasını geri al: önceki satır cümle sonuyla bitmiyorsa ve bu satır madde başı değilse birleştir
                if ($scope && !$bullet && !preg_match('/[.;:!?)]$/u', end($scope))) {
                    $scope[count($scope) - 1] .= ' ' . $item;
                } else {
                    $scope[] = $item;
                }
            }
            $out[] = ['name' => $name, 'scope' => implode("\n", $scope), 'fee' => $fee, 'is_optional' => $optional];
        }
        return $out;
    }

    // --- Yardımcılar ---

    /** Madde işaretlerini temizler. */
    private static function cleanItem(string $s): string
    {
        return trim(preg_replace('/^[\s\x{2022}\x{25AA}\x{25CF}\x{25A0}\x{F0A7}\x{F0B7}\-–•▪■●]+/u', '', $s));
    }

    /** Hücredeki ilk tutar ("12.500 USD", "$5,000.00", "10.000"); şablon yer tutucuysa null. */
    private static function money(string $s): ?float
    {
        if (!preg_match('/\d[\d.,\s]*\d|\d/u', $s, $m)) {
            return null;
        }
        $n = preg_replace('/\s+/u', '', $m[0]);
        $lastDot = strrpos($n, '.');
        $lastComma = strrpos($n, ',');
        if ($lastDot !== false && $lastComma !== false) {
            // İkisi de varsa sondaki ondalık ayracıdır: 12.500,50 / 12,500.50
            $dec = $lastDot > $lastComma ? '.' : ',';
            $n = str_replace($dec === '.' ? ',' : '.', '', $n);
            $n = str_replace(',', '.', $n);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $sep = $lastDot !== false ? '.' : ',';
            // Üçlü gruplar binlik ayracı (12.500 / 12,500), değilse ondalık (12,5)
            $n = preg_match('/^\d{1,3}(\\' . $sep . '\d{3})+$/', $n) ? str_replace($sep, '', $n) : str_replace(',', '.', $n);
        }
        return is_numeric($n) && (float) $n > 0 ? round((float) $n, 2) : null;
    }
}
