<?php

namespace App\Exceptions;

class ReportTooLargeException extends OrderWorkflowException
{
    public function __construct(int $rows, int $limit, string $format)
    {
        $hint = $format === 'xlsx'
            ? 'Persempit periode atau filter.'
            : 'Persempit periode atau filter, atau gunakan Excel.';

        parent::__construct(
            'Laporan terlalu besar untuk diekspor ('.number_format($rows, 0, ',', '.').' baris, maksimal '.number_format($limit, 0, ',', '.').'). '.$hint,
            422,
        );
    }
}
