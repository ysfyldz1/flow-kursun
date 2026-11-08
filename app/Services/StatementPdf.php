<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Dökümü müvekkilin yazışma dilinde PDF'e çevirir. */
final class StatementPdf
{
    public static function render(array $statement): string
    {
        $previous = Lang::locale();
        Lang::set($statement['locale']);
        try {
            $html = View::partial('pdf/statement', [
                's'     => $statement,
                'logo'  => self::logoDataUri(),
                'banks' => $statement['show_bank'] ? trim((string) Settings::get('billing.bank_accounts')) : '',
            ]);
            $pageLabel = __('billing.pdf_page');
        } finally {
            Lang::set($previous);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');      // Türkçe karakterleri destekler
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', BASE_PATH);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Toplam sayfa sayısı CSS sayacıyla alınamıyor; altbilginin sağına tuval üzerinden basılır
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $width = $metrics->getTextWidth("$pageLabel 1 / 1", $font, 7);
        $canvas->page_text($canvas->get_width() - 39.7 - $width, $canvas->get_height() - 38,"$pageLabel {PAGE_NUM} / {PAGE_COUNT}", $font, 7, [0.39, 0.45, 0.55]);

        return $dompdf->output();
    }

    public static function filename(array $s): string
    {
        return ($s['number'] ?: 'taslak-' . $s['id']) . '.pdf';
    }

    private static function logoDataUri(): ?string
    {
        $file = Settings::get('org.logo');
        $path = $file ? BASE_PATH . '/storage/uploads/' . basename($file) : null;
        if (!$path || !is_file($path)) {
            return null;
        }
        return 'data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path));
    }
}
