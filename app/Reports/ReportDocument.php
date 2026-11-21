<?php

namespace App\Reports;

final class ReportDocument
{
    /**
     * @param  array<int, array{label: string, value: string}>  $meta
     * @param  array<int, array{label: string, value: mixed, type: string}>  $kpis
     * @param  array<int, array{title: string, columns: array<int, array{label: string, type: string}>, rows: array<int, array<int, mixed>>, totals?: array<int, mixed>|null, empty?: string}>  $tables
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly string $fileTag = '',
        public readonly array $meta = [],
        public readonly array $kpis = [],
        public readonly array $tables = [],
        public readonly string $orientation = 'portrait',
        public readonly ?string $note = null,
    ) {
    }
}
