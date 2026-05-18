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
    protected $fullTitle;
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
        $this->fullTitle = $title ?: 'Data';
        $this->title = substr($this->fullTitle, 0, 31); // Excel sheet title max 31 chars
        $this->chartInfo = $chartInfo;
        $this->isLargeData = count($rows) > 1000;
        
        // Support both snake_case and camelCase from controller
        $this->currencyColumns = is_array($currencyColumns) ? $currencyColumns : [];
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

            // AI Decision: gunakan hanya currency_columns yang dikirim AI — tanpa fallback
            if ($this->isCurrencyCol($headerName, $normalizedCurrencyCols)) {
                $formats[$colLetter] = '"Rp" #,##0';
            }
            // Detect ID/Fixed String columns to format as Text
            elseif (preg_match('/(^id$|^no$|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|sku|ref)/i', $headerName)) {
                $formats[$colLetter] = NumberFormat::FORMAT_TEXT;
            }
            // Default: number dengan separator ribuan
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
     * Supports both exact match (after normalization) and partial match.
     */
    private function isCurrencyCol(string $headerName, array $normalizedCurrencyCols): bool
    {
        if ($this->isNonCurrencyLabel($headerName)) {
            return false;
        }

        // 1. Check against AI-provided currency columns
        if (!empty($normalizedCurrencyCols)) {
            $normalizedHeader = $this->normalizeLabel($headerName);
            
            // Exact match after normalization
            if (in_array($normalizedHeader, $normalizedCurrencyCols)) {
                return true;
            }

            // Partial match (to handle "Total Netto (Rp)" vs "total_netto")
            foreach ($normalizedCurrencyCols as $nc) {
                if (!empty($nc) && !$this->isNonCurrencyLabel($nc) && (strpos($normalizedHeader, $nc) !== false || strpos($nc, $normalizedHeader) !== false)) {
                    return true;
                }
            }
        }

        // 2. Fallback to keyword-based detection (Very robust)
        $h = strtolower($headerName);
        return (bool) preg_match('/(sales|amount|harga|nominal|tagihan|piutang|hutang|balance|netto|dpp|gpn|cogs|hpp|saldo|growth|realisasi|target|pencapaian|omset|revenue|pendapatan|penjualan|laba|profit|cost|biaya|nilai|total|sum|rupiah|rp)/i', $h);
    }

    private function isNonCurrencyLabel(string $label): bool
    {
        if (preg_match('/^\s*\d{4}\s*$/', $label)) {
            return true;
        }

        return (bool) preg_match('/(tahun|year|bulan|month|tanggal|date|periode|id|kode|code|no|nomor|qty|quantity|count|persen|persentase|percent|percentage|rate|cabang|dealer|pelanggan|produk|barang|item)/i', $label);
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
                'wrapText' => true,
            ],
        ]);

        // Explicitly set header row height - increased to handle potential wrapping
        $sheet->getRowDimension($startRow)->setRowHeight(35);

        // Add thin black/grey borders and center vertical alignment to the entire data table
        if ($lastRow >= $startRow) {
            $sheet->getStyle("A{$startRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'EEEEEE'], // Softer border color
                    ],
                    'outline' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                        'color' => ['rgb' => 'D32F2F'], // Red outline for the table
                    ],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
            
            // Set a comfortable row height for all data rows
            $sheet->getDefaultRowDimension()->setRowHeight(25); 

            if (!$this->isLargeData) {
                // Zebra striping - very subtle
                for ($row = $startRow + 1; $row <= $lastRow; $row += 2) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                    ]);
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
                $reportTitle = strtoupper($this->fullTitle ?: 'MBI DATA REPORT');
                $sheet->setCellValue('A1', $reportTitle);
                
                // Ensure title is fully visible even if columns are few
                $mergeEndCol = $lastCol;
                if ($lastColIndex < 6) {
                    $mergeEndCol = Coordinate::stringFromColumnIndex(max($lastColIndex, 6));
                }
                $sheet->mergeCells("A1:{$mergeEndCol}1");
                
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 18,
                        'color' => ['rgb' => 'D32F2F'], // Use brand color for title
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(50);

                // 2. Set Metadata at Row 2
                $generatedAt = 'Generated on: ' . date('d M Y H:i');
                $sheet->setCellValue('A2', $generatedAt);
                $sheet->mergeCells("A2:{$lastCol}2");
                
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 10,
                        'color' => ['rgb' => '999999'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // 3. Add column padding and Sizing
                $dataTableStart = $this->chartInfo ? 28 : 4;
                for ($columnIndex = 1; $columnIndex <= $lastColIndex; $columnIndex++) {
                    $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                    
                    // For smaller tables, use AutoSize for perfection
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                    
                    if ($this->isLargeData && $columnIndex > 25) {
                        $sheet->getColumnDimension($columnLetter)->setAutoSize(false);
                        $sheet->getColumnDimension($columnLetter)->setWidth(22);
                    } else {
                        $event->sheet->calculateColumnWidths(); 
                        $currentWidth = $sheet->getColumnDimension($columnLetter)->getWidth();
                        
                        // Set minimum width to avoid cramped columns
                        if ($currentWidth < 15) $currentWidth = 15;
                        
                        $sheet->getColumnDimension($columnLetter)->setAutoSize(false);
                        $sheet->getColumnDimension($columnLetter)->setWidth($currentWidth + 10); // Generous padding
                    }
                    
                    // Add indent to make text not touch the borders
                    $sheet->getStyle("{$columnLetter}{$dataTableStart}:{$columnLetter}{$sheet->getHighestRow()}")
                        ->getAlignment()
                        ->setIndent(2); // Increased indent
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
