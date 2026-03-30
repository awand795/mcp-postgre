<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
<<<<<<< HEAD
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
=======
use PhpOffice\PhpSpreadsheet\Cell\DataType;
>>>>>>> 69db8d709f0d43e7a2fc0f9038a2bb1d41f91906
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

<<<<<<< HEAD
class ChatTableExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithCharts, WithCustomStartCell, WithColumnFormatting, ShouldAutoSize
=======
class ChatTableExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithTitle, WithCharts, WithCustomStartCell, WithColumnFormatting
>>>>>>> 69db8d709f0d43e7a2fc0f9038a2bb1d41f91906
{
    protected $headers;
    protected $rows;
    protected $title;
    protected $chartInfo;

    /**
     * @param array $headers Table headers
     * @param array $rows Table data rows
     * @param string|null $title Sheet title (optional)
     * @param array|null $chartInfo Chart metadata (optional)
     */
    public function __construct(array $headers, array $rows, ?string $title = 'Data', ?array $chartInfo = null)
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->title = substr($title, 0, 31); // Excel sheet title max 31 chars
        $this->chartInfo = $chartInfo;
    }

    /**
     * @return string
     */
    public function startCell(): string
    {
        // If there is a chart, start data at row 25 to leave space for chart at top
        return $this->chartInfo ? 'A25' : 'A1';
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
        return $this->headers;
    }

    /**
<<<<<<< HEAD
     * @return array
     */
    public function columnFormats(): array
    {
        $formats = [];
        $lastColIndex = count($this->headers);
        
        for ($i = 1; $i <= $lastColIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $headerName = strtolower($this->headers[$i - 1] ?? '');

            // Detect ID/String columns to format as Text instead of Number
            // This prevents scientific notation for things like Invoice Numbers or IDs
            if (preg_match('/(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe)/i', $headerName)) {
                $formats[$colLetter] = NumberFormat::FORMAT_TEXT;
            } else {
                // For numeric values (Sales, Qty, Target, etc.), use a format that avoids scientific notation
                // and includes thousand separators for better readability in business context.
                $formats[$colLetter] = '#,##0'; 
            }
        }
        
        return $formats;
=======
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        // Convert large numbers to string with apostrophe prefix to prevent scientific notation
        return array_map(function($cell) {
            if ($cell === null || $cell === '') {
                return '';
            }
            
            // Check if cell is a large number (10+ digits)
            $strValue = (string)$cell;
            
            // Remove apostrophe if already present from frontend
            if (strpos($strValue, "'") === 0) {
                return $strValue;
            }
            
            // Check if it's a large number that would trigger scientific notation
            if (preg_match('/^\d{10,}$/', $strValue)) {
                return "'" . $strValue;
            }
            
            return $cell;
        }, $row);
>>>>>>> 69db8d709f0d43e7a2fc0f9038a2bb1d41f91906
    }

    /**
     * @param Worksheet $sheet
     *
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $startRow = $this->chartInfo ? 25 : 1;
        $lastCol = $sheet->getHighestColumn();
<<<<<<< HEAD
        $lastRow = $sheet->getHighestRow();
        
        // Apply header styling
=======

>>>>>>> 69db8d709f0d43e7a2fc0f9038a2bb1d41f91906
        $sheet->getStyle("A{$startRow}:{$lastCol}{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F53003']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

<<<<<<< HEAD
        // Add borders to the entire table if data exists
        if ($lastRow >= $startRow) {
            $sheet->getStyle("A{$startRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }
=======
        // Format semua data cells sebagai text untuk mencegah notasi ilmiah (1E+09)
        $dataStartRow = $startRow + 1; // Data starts after header
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
>>>>>>> 69db8d709f0d43e7a2fc0f9038a2bb1d41f91906
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

        // Data starts at row 26 (header is at 25)
        // Labels are in column B (A is 'No', B is 'Label')
        $categories = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$this->title}'!\$B\$26:\$B$" . (25 + $dataPoints), null, $dataPoints),
        ];

        $values = [];
        $labels = [];
        for ($i = 0; $i < $datasetCount; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i); // Starting from column C
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$this->title}'!\${$colLetter}\$25", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$this->title}'!\${$colLetter}\$26:\${$colLetter}\$" . (25 + $dataPoints), null, $dataPoints);
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

        $chart->setTopLeftPosition('A1');
        $chart->setBottomRightPosition('L23');

        return $chart;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array
     */
    public function columnFormats(): array
    {
        $formats = [];
        $colCount = count($this->headers);

        // Format semua kolom yang mungkin berisi angka besar sebagai TEXT
        // Ini mencegah Excel mengubah angka besar jadi notasi ilmiah (1E+09)
        for ($i = 1; $i <= $colCount; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $formats[$colLetter] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }
}
