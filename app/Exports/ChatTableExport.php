<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ChatTableExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithCharts, WithCustomStartCell, WithColumnFormatting, WithEvents
{
    protected $headers;
    protected $rows;
    protected $title;
    protected $chartInfo;
    protected $isLargeData;
    protected $currencyColumns;

    /**
     * @param array $headers Table headers
     * @param array $rows Table data rows
     * @param string|null $title Sheet title (optional)
     * @param array|null $chartInfo Chart metadata (optional)
     * @param array $currencyColumns Identified currency columns (optional)
     */
    public function __construct(array $headers, array $rows, ?string $title = 'Data', ?array $chartInfo = null, array $currencyColumns = [])
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->title = substr($title, 0, 31); // Excel sheet title max 31 chars
        $this->chartInfo = $chartInfo;
        $this->isLargeData = count($rows) > 1000;
        $this->currencyColumns = array_map('strtolower', $currencyColumns);
    }

    /**
     * @return string
     */
    public function startCell(): string
    {
        // Layout: 
        // A1: Title
        // A2: Metadata (Date)
        // A3: Empty
        // A4-A26: Chart (if exists)
        // A28: Table Header (if chart exists) or A4: Table Header (if no chart)
        
        if ($this->chartInfo) {
            return 'A28';
        }
        return 'A4';
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Format underscores to spaces and capitalize (e.g. nama_cabang -> Nama Cabang)
        return array_map(function($header) {
            $formatted = str_replace(['_', '-'], ' ', $header);
            return mb_convert_case($formatted, MB_CASE_TITLE, "UTF-8");
        }, $this->headers);
    }

    /**
     * @return array
     */
    public function columnFormats(): array
    {
        $formats = [];
        $lastColIndex = count($this->headers);

        // Normalize currencyColumns once for comparison
        $normalizedCurrencyCols = array_map(function($col) {
            return $this->normalizeLabel($col);
        }, $this->currencyColumns);

        for ($i = 1; $i <= $lastColIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $headerName = $this->headers[$i - 1] ?? '';

            // 1. AI Decision Priority: gunakan isCurrencyCol yang mendukung partial match
            if ($this->isCurrencyCol($headerName, $normalizedCurrencyCols)) {
                $formats[$colLetter] = '"Rp" #,##0';
            }
            // 2. Detect ID/Fixed String columns to format as Text
            elseif (preg_match('/(^id$|^no$|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|sku|ref)/i', $headerName)) {
                $formats[$colLetter] = NumberFormat::FORMAT_TEXT;
            }
            // 3. Fallback Keyword Detection untuk kolom currency yang tidak ter-cover AI
            elseif (preg_match('/(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|realisasi|target|pencapaian|omset|revenue|pendapatan|penjualan|laba|profit|nilai|total_)/i', $headerName)) {
                $formats[$colLetter] = '"Rp" #,##0';
            }
            // 4. Default: number dengan separator ribuan
            else {
                $formats[$colLetter] = '#,##0';
            }
        }

        return $formats;
    }

    /**
     * Normalize label for comparison with currency columns metadata.
     * Converts "Total Netto" -> "total_netto", "GPN" -> "gpn", etc.
     * Also handles display names like "Total Netto" matching DB name "total_netto".
     */
    private function normalizeLabel(string $label): string
    {
        // Convert to lowercase
        $normalized = strtolower($label);

        // Replace spaces with underscores (reverse of toHumanLabel)
        $normalized = preg_replace('/\s+/', '_', $normalized);

        // Remove non-alphanumeric chars except underscore
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);

        // Remove extra underscores
        $normalized = preg_replace('/_+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        return $normalized;
    }

    /**
     * Check if a column should be treated as currency.
     * Supports both exact match (after normalization) and keyword fallback.
     */
    private function isCurrencyCol(string $headerName, array $normalizedCurrencyCols): bool
    {
        $normalizedHeader = $this->normalizeLabel($headerName);

        // 1. Exact match after normalization
        if (in_array($normalizedHeader, $normalizedCurrencyCols)) {
            return true;
        }

        // 2. Partial match: apakah header mengandung salah satu currency col atau sebaliknya
        foreach ($normalizedCurrencyCols as $col) {
            if (!empty($col) && (str_contains($normalizedHeader, $col) || str_contains($col, $normalizedHeader))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Worksheet $sheet
     *
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $startRow = $this->chartInfo ? 28 : 4;
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        
        // Apply header styling (Premium Red Theme)
        $sheet->getStyle("A{$startRow}:{$lastCol}{$startRow}")->applyFromArray([
            'font' => [
                'bold' => true, 
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
                'startColor' => ['rgb' => 'D32F2F'] // Slightly deeper red for premium look
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Explicitly set header row height
        $sheet->getRowDimension($startRow)->setRowHeight(25);

        // Add thin black/grey borders and center vertical alignment to the entire data table
        if ($lastRow >= $startRow) {
            $sheet->getStyle("A{$startRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
            
            // Set a comfortable row height for all data rows (Spasi)
            // Use default for speed if data is large
            if ($this->isLargeData) {
                $sheet->getDefaultRowDimension()->setRowHeight(20);
            } else {
                // Zebra striping - disabled for very large tables for performance
                for ($row = $startRow + 1; $row <= $lastRow; $row += 2) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDFDFD']],
                    ]);
                }

                for ($row = $startRow + 1; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastColIndex = Coordinate::columnIndexFromString($lastCol);
                
                // 1. Set Title at Row 1
                $reportTitle = strtoupper($this->title ?: 'MBI DATA REPORT');
                $sheet->setCellValue('A1', $reportTitle);
                $sheet->mergeCells("A1:{$lastCol}1");
                
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '333333'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // 2. Set Metadata at Row 2
                $generatedAt = 'Generated on: ' . date('d M Y H:i');
                $sheet->setCellValue('A2', $generatedAt);
                $sheet->mergeCells("A2:{$lastCol}2");
                
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 10,
                        'color' => ['rgb' => '666666'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // 3. Add column padding (Extra Spasi) and Sizing
                $dataTableStart = $this->chartInfo ? 28 : 4;
                foreach (range(1, $lastColIndex) as $columnIndex) {
                    $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                    
                    if ($this->isLargeData) {
                        // For large tables, use a fixed comfortable width for speed
                        $sheet->getColumnDimension($columnLetter)->setWidth(20);
                    } else {
                        // For smaller tables, use AutoSize for perfection
                        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                        // Force calculate width so we can add padding
                        $event->sheet->calculateColumnWidths(); 
                        $currentWidth = $sheet->getColumnDimension($columnLetter)->getWidth();
                        $sheet->getColumnDimension($columnLetter)->setAutoSize(false);
                        $sheet->getColumnDimension($columnLetter)->setWidth($currentWidth + 4);
                    }
                    
                    // Add indent to make text not touch the borders, starting from data table headers
                    $sheet->getStyle("{$columnLetter}{$dataTableStart}:{$columnLetter}{$sheet->getHighestRow()}")
                        ->getAlignment()
                        ->setIndent(1);
                }
            },
        ];
    }

    /**
     * @return Chart|Chart[]
     */
    public function charts()
    {
        if (!$this->chartInfo) {
            return [];
        }

        $type = $this->chartInfo['type'] ?? 'bar';
        $title = $this->chartInfo['title'] ?? 'Chart Data';
        $dataPoints = $this->chartInfo['dataPoints'] ?? count($this->rows);
        $datasetCount = $this->chartInfo['datasetCount'] ?? 1;

        // Map internal types to PhpSpreadsheet types
        $chartType = DataSeries::TYPE_BARCHART;
        if ($type === 'line') $chartType = DataSeries::TYPE_LINECHART;
        if ($type === 'pie') $chartType = DataSeries::TYPE_PIECHART;
        if ($type === 'doughnut') $chartType = DataSeries::TYPE_DONUTCHART;

        // Data starts at row 29 (header is at 28)
        // Labels are in column B (A is 'No', B is 'Label')
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$this->title}'!\$B\$29:\$B$" . (28 + $dataPoints), null, $dataPoints),
        ];

        $values = [];
        $labels = [];
        for ($i = 0; $i < $datasetCount; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(3 + $i); // Starting from column C
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$this->title}'!\${$colLetter}\$28", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$this->title}'!\${$colLetter}\$29:\${$colLetter}\$" . (28 + $dataPoints), null, $dataPoints);
        }

        $series = new DataSeries(
            $chartType,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chartTitle = new ChartTitle($title);

        $chart = new Chart(
            'chart1',
            $chartTitle,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('A4');
        $chart->setBottomRightPosition('L26');

        return $chart;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }
}
