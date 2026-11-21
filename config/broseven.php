<?php

return [
    'return_window_days' => (int) env('RETURN_WINDOW_DAYS', 0),

    'reports' => [
        'pdf_max_rows' => (int) env('REPORT_PDF_MAX_ROWS', 1500),
        'excel_max_rows' => (int) env('REPORT_EXCEL_MAX_ROWS', 20000),
        'activity_max_rows' => 1000,
    ],
];
