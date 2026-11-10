<?php

declare(strict_types=1);

namespace Modules\Advances;

use App\Core\Lang;
use App\Core\View;
use App\Services\StatementPdf;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Avans ekstresini müvekkilin yazışma dilinde, döküm PDF'iyle aynı görünümde üretir. */
final class AdvancePdf
{
    public static function render(array $client, string $currency, array $ledger, string $from, string $to): string
    {
        $previous = Lang::locale();
        Lang::set($client['locale']);
        try {
            $html = View::partial('Advances::pdf', [
                'c'        => $client,
                'currency' => $currency,
                'ledger'   => $ledger,
                'from'     => $from,
                'to'       => $to,
                'logo'     => StatementPdf::logoDataUri(),
            ]);
            $pageLabel = __('billing.pdf_page');
        } finally {
            Lang::set($previous);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('chroot', BASE_PATH);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $width = $metrics->getTextWidth("$pageLabel 1 / 1", $font, 7);
        $canvas->page_text($canvas->get_width() - 39.7 - $width, $canvas->get_height() - 38, "$pageLabel {PAGE_NUM} / {PAGE_COUNT}", $font, 7, [0.39, 0.45, 0.55]);

        return $dompdf->output();
    }

    public static function filename(array $client, string $currency, string $from, string $to): string
    {
        return 'avans-' . $client['code'] . '-' . $currency . '-' . $from . '_' . $to . '.pdf';
    }
}
