<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: {{ $fontSize }}pt;
            color: #1a1a1a;
            padding: {{ $colCount > 10 ? '10px' : '16px' }};
        }

        .report-header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #D32F2F;
            padding-bottom: 6px;
        }

        .report-title {
            font-size: {{ min(14, max(10, 16 - ($colCount * 0.3))) }}pt;
            font-weight: bold;
            color: #D32F2F;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .report-meta {
            font-size: {{ $fontSize - 1 }}pt;
            color: #666;
            font-style: italic;
        }

        .chart-container {
            text-align: center;
            margin: 12px 0;
            page-break-inside: avoid;
        }

        .chart-container img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: auto;
            table-layout: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        th {
            background-color: #C62828;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 6px 8px;
            font-size: {{ $fontSize }}pt;
            border: 1px solid #B71C1C;
        }

        td {
            padding: 5px 8px;
            border: 1px solid #ddd;
            font-size: {{ $fontSize - 0.5 }}pt;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        td.text-col {
            text-align: left;
        }

        td.number-col {
            text-align: right;
            white-space: nowrap;
        }

        td.currency-col {
            text-align: right;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .footer {
            margin-top: 14px;
            text-align: center;
            font-size: {{ $fontSize - 1.5 }}pt;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 6px;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="report-title">{{ $title }}</div>
        <div class="report-meta">{{ $generatedAt }} &bull; {{ count($rows) }} baris &bull; {{ $colCount }} kolom</div>
    </div>

    @if($chartImage)
    <div class="chart-container">
        <img src="{{ $chartImage }}" alt="Chart">
    </div>
    @endif

    @if(count($rows) > 0)
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $rowIndex => $row)
            <tr>
                @foreach($row as $colIndex => $cell)
                    @php
                        $colType = $columnTypes[$colIndex] ?? 'text';
                        $class = $colType === 'number' ? 'number-col' : ($colType === 'currency' ? 'currency-col' : 'text-col');

                        if ($colType === 'currency' && is_numeric($cell) && $cell !== '' && $cell !== null) {
                            // Format angka penuh: 5500000 → Rp 5.500.000
                            $cell = 'Rp ' . number_format((float)$cell, 0, ',', '.');
                        } elseif ($colType === 'currency' && is_string($cell) && preg_match('/^[\d.,]+$/', trim(str_replace(['Rp', ' '], '', $cell)))) {
                            // Sudah formatted sebagai string currency — pastikan format konsisten
                            $numericVal = (float) preg_replace('/[^0-9,]/', '', str_replace(['.'], '', str_replace(',', '.', trim(str_replace(['Rp', ' '], '', $cell)))));
                            if ($numericVal > 0) {
                                $cell = 'Rp ' . number_format($numericVal, 0, ',', '.');
                            }
                        } elseif ($colType === 'number' && is_numeric($cell)) {
                            // Format angka biasa dengan separator ribuan
                            $cell = number_format((float)$cell, 0, ',', '.');
                        }
                    @endphp
                    <td class="{{ $class }}">{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">Tidak ada data untuk ditampilkan</div>
    @endif

    <div class="footer">
        Generated by darkotech AI &bull; {{ date('Y') }} &bull; Total: {{ count($rows) }} baris
    </div>
</body>
</html>
