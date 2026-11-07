<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/** Küçük tablo okuyucu: .xlsx (ilk sayfa) ve .csv/.txt satırlarını hücre dizisi olarak döndürür. */
final class Spreadsheet
{
    public static function rows(string $path, string $originalName): array
    {
        return strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'xlsx'
            ? self::xlsx($path)
            : self::csv($path);
    }

    private static function csv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1254');   // Türkçe Excel'in eski CSV'si
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $first = $lines[0] ?? '';
        // Ayırıcı: Türkçe Excel ';', diğerleri ',' ya da sekme
        $delim = substr_count($first, ';') >= max(substr_count($first, ','), substr_count($first, "\t")) ? ';'
            : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $rows[] = str_getcsv($line, $delim);
            }
        }
        return $rows;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                // Biçimli hücrede metin birden fazla <r><t> parçasındadır
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            return [];
        }
        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::column(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $value = match ($type) {
                    's'         => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                };
                $cells[$col] = $value;
            }
            if ($cells && implode('', $cells) !== '') {
                $out = array_fill(0, max(array_keys($cells)) + 1, '');
                $rows[] = array_replace($out, $cells);
            }
        }
        return $rows;
    }

    /** "A" → 0, "AB" → 27 */
    private static function column(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
