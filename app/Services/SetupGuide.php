<?php

declare(strict_types=1);

namespace App\Services;

/** docs/KURULUM.md'yi Ayarlar→Kurulum rehberi'nde gösterir; "<alan-adı>" kurulumun gerçek adresiyle değiştirilir. */
final class SetupGuide
{
    public static function html(): string
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $md = str_replace('<alan-adı>', $host, (string) file_get_contents(BASE_PATH . '/docs/KURULUM.md'));
        $html = '';
        $list = null;
        $table = false;
        $close = function () use (&$html, &$list, &$table): void {
            if ($list) {
                $html .= "</li></$list>";
                $list = null;
            }
            if ($table) {
                $html .= '</table>';
                $table = false;
            }
        };

        foreach (preg_split('/\r?\n/', $md) ?: [] as $line) {
            if (trim($line) === '') {
                continue;   // liste boş satırla bölünmez; açık blok yeni başlık gelince kapanır
            }
            if (preg_match('/^(#{1,3}) (.+)$/', $line, $m)) {
                $close();
                $level = strlen($m[1]) + 1;
                $html .= "<h$level>" . self::inline($m[2]) . "</h$level>";
            } elseif (preg_match('/^\s*\|/', $line)) {
                if (preg_match('/^\s*\|[\s:|-]+\|\s*$/', $line)) {
                    continue;   // başlık ayırıcı satır
                }
                if (!$table) {
                    $close();
                    $html .= '<table class="table table-compact">';
                    $table = true;
                }
                $cells = array_map('trim', explode('|', trim(trim($line), '|')));
                $html .= '<tr><td>' . implode('</td><td>', array_map([self::class, 'inline'], $cells)) . '</td></tr>';
            } elseif (preg_match('/^(?:- |\d+\. )(.+)$/', $line, $m)) {
                $tag = $line[0] === '-' ? 'ul' : 'ol';
                if ($table || $list !== $tag) {
                    $close();
                    $html .= "<$tag><li>";
                    $list = $tag;
                } else {
                    $html .= '</li><li>';
                }
                $html .= self::inline($m[1]);
            } elseif ($list) {
                $html .= '<br>' . self::inline(trim($line));   // girintili devam satırı
            } else {
                $close();
                $html .= '<p>' . self::inline($line) . '</p>';
            }
        }
        $close();
        return $html;
    }

    private static function inline(string $text): string
    {
        $text = e($text);
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    }
}
