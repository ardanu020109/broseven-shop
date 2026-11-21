<?php

namespace App\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfExporter
{
    public function render(ReportDocument $document, array $context): string
    {
        @set_time_limit(180);
        $this->ensureMemory();

        $html = view('reports.document', array_merge($context, [
            'doc' => $document,
            'print' => false,
        ]))->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $document->orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function ensureMemory(): void
    {
        $current = trim((string) ini_get('memory_limit'));

        if ($current === '-1' || $current === '') {
            return;
        }

        $unit = strtolower(substr($current, -1));
        $number = (int) $current;
        $bytes = match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        if ($bytes < 512 * 1024 ** 2) {
            @ini_set('memory_limit', '512M');
        }
    }
}
