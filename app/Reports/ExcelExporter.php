<?php

namespace App\Reports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ExcelExporter
{
    public function download(ReportDocument $document, array $context, string $filename): BinaryFileResponse
    {
        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);
        $this->purgeOld($directory);

        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.xlsx';

        $options = new Options();
        $options->setColumnWidth(30, 1);
        $options->setColumnWidth(22, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

        $writer = new Writer($options);

        try {
            $writer->openToFile($path);
            $this->write($writer, $document, $context);
            $writer->close();
        } catch (Throwable $e) {
            try {
                $writer->close();
            } catch (Throwable) {
                // penulis belum terbuka, abaikan
            }

            if (is_file($path)) {
                @unlink($path);
            }

            throw $e;
        }

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ])
            ->deleteFileAfterSend(true);
    }

    private function write(Writer $writer, ReportDocument $document, array $context): void
    {
        $styles = $this->styles($context['brand']['dark']);
        $used = ['ringkasan'];

        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow($this->strings([$document->title], $styles['title']));
        $writer->addRow($this->strings([$context['company']->store_name], $styles['bold']));
        $writer->addRow($this->blank());

        foreach ($document->meta as $item) {
            $writer->addRow(new Row([
                new StringCell($item['label'], $styles['bold']),
                new StringCell($item['value'], null),
            ]));
        }

        if ($document->kpis !== []) {
            $writer->addRow($this->blank());
            $writer->addRow($this->strings(['Ringkasan', 'Nilai'], $styles['header']));

            foreach ($document->kpis as $kpi) {
                $writer->addRow(new Row([
                    new StringCell($kpi['label'], $styles['bold']),
                    $this->cell($kpi['value'], $kpi['type'], $styles),
                ]));
            }
        }

        if ($document->note !== null) {
            $writer->addRow($this->blank());
            $writer->addRow($this->strings([$document->note], null));
        }

        $writer->addRow($this->blank());
        $writer->addRow($this->strings(['Dibuat oleh', $context['generatedBy']], null));
        $writer->addRow($this->strings(['Dibuat pada', $context['generatedAt']], null));

        foreach ($document->tables as $table) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->sheetName($table['title'], $used));

            $writer->addRow($this->strings(array_column($table['columns'], 'label'), $styles['header']));

            if ($table['rows'] === []) {
                $writer->addRow($this->strings([$table['empty'] ?? 'Tidak ada data.'], null));

                continue;
            }

            foreach ($table['rows'] as $row) {
                $cells = [];

                foreach ($table['columns'] as $index => $column) {
                    $cells[] = $this->cell($row[$index] ?? null, $column['type'], $styles);
                }

                $writer->addRow(new Row($cells));
            }

            if (! empty($table['totals'])) {
                $cells = [];

                foreach ($table['columns'] as $index => $column) {
                    $value = $table['totals'][$index] ?? null;

                    $cells[] = $value === null
                        ? new StringCell('', $styles['bold'])
                        : $this->cell($value, $column['type'], $styles, true);
                }

                $writer->addRow(new Row($cells));
            }
        }
    }

    private function cell(mixed $value, string $type, array $styles, bool $bold = false): Cell
    {
        if ($value === null || $value === '') {
            return new StringCell('-', $bold ? $styles['bold'] : null);
        }

        return match ($type) {
            'money', 'int' => new NumericCell((float) $value, $bold ? $styles['numberBold'] : $styles['number']),
            'minutes' => new NumericCell((float) $value, $bold ? $styles['decimalBold'] : $styles['decimal']),
            default => new StringCell((string) $value, $bold ? $styles['bold'] : null),
        };
    }

    private function strings(array $values, ?Style $style): Row
    {
        return new Row(array_map(fn ($value) => new StringCell((string) $value, $style), $values));
    }

    private function blank(): Row
    {
        return new Row([new StringCell('', null)]);
    }

    private function styles(string $darkHex): array
    {
        $background = strtoupper(ltrim($darkHex, '#'));

        return [
            'title' => (new Style())->setFontBold()->setFontSize(14),
            'bold' => (new Style())->setFontBold(),
            'header' => (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor($background),
            'number' => (new Style())->setFormat('#,##0'),
            'numberBold' => (new Style())->setFontBold()->setFormat('#,##0'),
            'decimal' => (new Style())->setFormat('0.0'),
            'decimalBold' => (new Style())->setFontBold()->setFormat('0.0'),
        ];
    }

    private function sheetName(string $title, array &$used): string
    {
        $clean = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title) ?? '');
        $clean = mb_substr($clean !== '' ? $clean : 'Data', 0, 28);

        $name = $clean;
        $counter = 2;

        while (in_array(mb_strtolower($name), $used, true)) {
            $name = $clean.' '.$counter++;
        }

        $used[] = mb_strtolower($name);

        return $name;
    }

    private function purgeOld(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.xlsx') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
    }
}
