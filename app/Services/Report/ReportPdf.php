<?php

namespace App\Services\Report;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Draws a ReportDocument as a PDF on landscape A4, with the letterhead on top and a signature block at the end.
 */
class ReportPdf
{
    public function render(ReportDocument $document): string
    {
        $pdf = Pdf::loadView('reports.pdf', ['document' => $document])
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(
            $canvas->get_width() - 120,
            $canvas->get_height() - 28,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $font,
            8,
            [0.35, 0.35, 0.35],
        );

        return (string) $dompdf->output();
    }
}
