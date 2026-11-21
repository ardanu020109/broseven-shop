@use('App\Reports\ReportFormat')
@php
    $dark = $brand['dark'];
    $primary = $brand['primary'];
    $contact = collect([
        $company->phone ? 'Telp. '.$company->phone : null,
        $company->email,
    ])->filter()->implode('  |  ');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->title }}</title>
    <style>
        @page { size: A4 {{ $doc->orientation }}; margin: 26px 28px 46px 28px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
        h1 { font-size: 16px; margin: 12px 0 4px; color: {{ $dark }}; }
        h2 { font-size: 11px; margin: 16px 0 6px; color: {{ $dark }}; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        table { border-collapse: collapse; }
        table.head { width: 100%; border-bottom: 2px solid {{ $primary }}; padding-bottom: 6px; }
        table.head td { vertical-align: middle; padding: 0 0 6px 0; }
        .logo-cell { width: 54px; }
        .logo-cell img { height: 42px; }
        .company { font-size: 13px; font-weight: bold; color: {{ $dark }}; }
        table.meta td { padding: 1px 12px 1px 0; vertical-align: top; }
        table.meta td.label { color: #6b7280; white-space: nowrap; }
        table.kpi { width: 100%; margin-top: 10px; border-spacing: 6px; border-collapse: separate; }
        table.kpi td { width: 33.33%; border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        table.kpi td.empty { border: 0; }
        .kpi-label { font-size: 8px; color: #6b7280; }
        .kpi-value { font-size: 12px; font-weight: bold; color: {{ $dark }}; margin-top: 2px; }
        table.data { width: 100%; margin-top: 4px; }
        table.data th { background: {{ $dark }}; color: #ffffff; padding: 5px 6px; font-size: 8.5px; text-align: left; border: 1px solid {{ $dark }}; }
        table.data td { padding: 4px 6px; border: 1px solid #d1d5db; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        table.data tr.alt td { background: #f3f4f6; }
        table.data tr.total td { font-weight: bold; background: #e5e7eb; }
        table.data .num { text-align: right; white-space: nowrap; }
        .note { margin-top: 12px; padding: 6px 8px; background: #f9fafb; border: 1px solid #e5e7eb; color: #4b5563; }
        .printed { margin-top: 14px; color: #6b7280; font-size: 8px; }
        .pdf-footer { position: fixed; left: 0; right: 0; bottom: -30px; text-align: center; font-size: 8px; color: #6b7280; }
        .pdf-footer .pn:before { content: "Halaman " counter(page); }
    </style>
</head>
<body>
@unless ($print)
    <div class="pdf-footer"><span class="pn"></span></div>
@endunless

<table class="head">
    <tr>
        @if ($logo)
            <td class="logo-cell"><img src="{{ $logo }}" alt=""></td>
        @endif
        <td>
            <div class="company">{{ $company->store_name }}</div>
            @if ($company->address)
                <div class="muted">{{ $company->address }}</div>
            @endif
            @if ($contact !== '')
                <div class="muted">{{ $contact }}</div>
            @endif
        </td>
        <td class="right muted">Dicetak {{ $generatedAt }}<br>oleh {{ $generatedBy }}</td>
    </tr>
</table>

<h1>{{ $doc->title }}</h1>

<table class="meta">
    @foreach ($doc->meta as $item)
        <tr>
            <td class="label">{{ $item['label'] }}</td>
            <td>: {{ $item['value'] }}</td>
        </tr>
    @endforeach
</table>

@if ($doc->kpis !== [])
    <table class="kpi">
        @foreach (array_chunk($doc->kpis, 3) as $chunk)
            <tr>
                @foreach ($chunk as $kpi)
                    <td>
                        <div class="kpi-label">{{ $kpi['label'] }}</div>
                        <div class="kpi-value">{{ ReportFormat::cell($kpi['value'], $kpi['type']) }}</div>
                    </td>
                @endforeach
                @for ($i = count($chunk); $i < 3; $i++)
                    <td class="empty"></td>
                @endfor
            </tr>
        @endforeach
    </table>
@endif

@foreach ($doc->tables as $table)
    <h2>{{ $table['title'] }}</h2>
    <table class="data">
        <thead>
            <tr>
                @foreach ($table['columns'] as $column)
                    <th class="{{ ReportFormat::isNumeric($column['type']) ? 'num' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($table['rows'] as $index => $row)
                <tr class="{{ $index % 2 ? 'alt' : '' }}">
                    @foreach ($table['columns'] as $position => $column)
                        <td class="{{ ReportFormat::isNumeric($column['type']) ? 'num' : '' }}">{{ ReportFormat::cell($row[$position] ?? null, $column['type']) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($table['columns']) }}" class="muted">{{ $table['empty'] ?? 'Tidak ada data.' }}</td>
                </tr>
            @endforelse

            @if (! empty($table['totals']) && count($table['rows']) > 0)
                <tr class="total">
                    @foreach ($table['columns'] as $position => $column)
                        @php($total = $table['totals'][$position] ?? null)
                        <td class="{{ ReportFormat::isNumeric($column['type']) ? 'num' : '' }}">{{ $total === null ? '' : ReportFormat::cell($total, $column['type']) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
@endforeach

@if ($doc->note)
    <div class="note">{{ $doc->note }}</div>
@endif

<div class="printed">Dokumen ini dibuat otomatis oleh sistem {{ $company->store_name }}.</div>
</body>
</html>
