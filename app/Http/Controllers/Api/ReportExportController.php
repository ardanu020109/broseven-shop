<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Reports\ExcelExporter;
use App\Reports\PdfExporter;
use App\Reports\ReportContext;
use App\Reports\ReportDocument;
use App\Reports\ReportDocumentBuilder;
use App\Services\AuditLogger;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function __construct(
        private ReportDocumentBuilder $builder,
        private PdfExporter $pdf,
        private ExcelExporter $excel,
        private AuditLogger $audit,
    ) {
    }

    public function revenue(Request $request): Response
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'format' => $this->formatRule(),
            'cashier_id' => ['nullable', 'integer', 'min:1'],
        ]));

        $format = $request->input('format');
        $cashierId = $request->filled('cashier_id') ? $request->integer('cashier_id') : null;

        $document = $this->builder->revenue(ReportPeriod::fromRequest($request), $cashierId, $this->limit($format), $format);

        return $this->deliver($request, $document, $format);
    }

    public function cashiers(Request $request): Response
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'format' => $this->formatRule(),
            'cashier_id' => ['nullable', 'integer', 'min:1'],
        ]));

        $format = $request->input('format');
        $cashierId = $request->filled('cashier_id') ? $request->integer('cashier_id') : null;

        $document = $this->builder->cashiers(ReportPeriod::fromRequest($request), $cashierId, $this->limit($format), $format);

        return $this->deliver($request, $document, $format);
    }

    public function inventory(Request $request): Response
    {
        $filters = $request->validate([
            'format' => $this->formatRule(),
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['tersedia', 'menipis', 'habis', 'nonaktif'])],
            'sort' => ['nullable', Rule::in(['name', 'stock', 'selling_price'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $format = $filters['format'];

        $document = $this->builder->inventory($filters, $this->limit($format), $format);

        return $this->deliver($request, $document, $format);
    }

    public function cashierSelf(Request $request): Response
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'format' => $this->formatRule(),
            'status' => ['nullable', Rule::in(['SELESAI', 'DIBATALKAN'])],
        ]));

        $format = $request->input('format');

        $document = $this->builder->cashierSelf(
            ReportPeriod::fromRequest($request),
            $request->user(),
            $request->input('status') ?: null,
            $this->limit($format),
            $format,
        );

        return $this->deliver($request, $document, $format);
    }

    private function deliver(Request $request, ReportDocument $document, string $format): Response
    {
        $user = $request->user();

        $this->audit->log(
            'report.exported',
            null,
            "Ekspor {$document->title} ({$format}).",
            null,
            ['report' => $document->slug, 'format' => $format, 'filters' => $request->except(['format'])],
        );

        $context = ReportContext::for($user);

        if ($format === 'xlsx') {
            return $this->excel->download($document, $context, $this->filename($document, 'xlsx'));
        }

        if ($format === 'print') {
            $html = view('reports.document', array_merge($context, ['doc' => $document, 'print' => true]))->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        $pdf = $this->pdf->render($document, $context);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $this->filename($document, 'pdf')),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function formatRule(): array
    {
        return ['required', Rule::in(['pdf', 'xlsx', 'print'])];
    }

    private function limit(string $format): int
    {
        return $format === 'xlsx'
            ? (int) config('broseven.reports.excel_max_rows')
            : (int) config('broseven.reports.pdf_max_rows');
    }

    private function filename(ReportDocument $document, string $extension): string
    {
        $tag = preg_replace('/[^0-9A-Za-z_-]/', '', $document->fileTag) ?? '';
        $stamp = now()->format('Ymd-His');

        return $document->slug.($tag !== '' ? "_{$tag}" : '')."_{$stamp}.{$extension}";
    }
}
