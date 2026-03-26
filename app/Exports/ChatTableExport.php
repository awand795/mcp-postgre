<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ChatTableExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnFormatting, WithEvents
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
     * @param Worksheet $sheet
     *
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Get the last column
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        
        // Header range (e.g., A1:Z1)
        $headerRange = "A1:{$lastCol}1";
        
        // Style header row
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F53003'], // Darko theme color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Auto-size all columns
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Style data rows
        if ($lastRow > 1) {
            $dataRange = "A2:{$lastCol}{$lastRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
            ]);

            // Alternate row colors (zebra striping)
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 === 0) {
                    $rowRange = "A{$row}:{$lastCol}{$row}";
                    $sheet->getStyle($rowRange)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F9FAFB'],
                        ],
                    ]);
                }
            }
            
            // Style summary row differently
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'F53003'], 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEE2E2'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }

        // Freeze header row
        $sheet->freezePane('A2');
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
        // Auto-detect and format currency/number columns
        $formats = [];
        
        foreach ($this->headers as $index => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $headerLower = strtolower($header);
            
            // Detect currency columns
            if (str_contains($headerLower, 'total') || 
                str_contains($headerLower, 'amount') || 
                str_contains($headerLower, 'dpp') ||
                str_contains($headerLower, 'netto') || 
                str_contains($headerLower, 'cogs') || 
                str_contains($headerLower, 'harga') ||
                str_contains($headerLower, 'price') || 
                str_contains($headerLower, 'nominal') ||
                str_contains($headerLower, 'sales') || 
                str_contains($headerLower, 'laba') || 
                str_contains($headerLower, 'profit')) {
                $formats[$colLetter] = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
            }
        }

        return $formats;
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Add chart info if available
                if ($this->chartInfo) {
                    $lastRow = $sheet->getHighestRow() + 2;
                    
                    // Add chart metadata
                    $sheet->setCellValue("A{$lastRow}", 'Chart Information:');
                    $sheet->getStyle("A{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'F53003']],
                    ]);
                    
                    $lastRow++;
                    $sheet->setCellValue("A{$lastRow}", "Type: " . ($this->chartInfo['type'] ?? 'N/A'));
                    $lastRow++;
                    $sheet->setCellValue("A{$lastRow}", "Title: " . ($this->chartInfo['title'] ?? 'N/A'));
                    $lastRow++;
                    $sheet->setCellValue("A{$lastRow}", "Datasets: " . ($this->chartInfo['datasetCount'] ?? 0));
                    $lastRow++;
                    $sheet->setCellValue("A{$lastRow}", "Data Points: " . ($this->chartInfo['dataPoints'] ?? 0));
                    $lastRow++;
                    $sheet->setCellValue("A{$lastRow}", "Exported: " . date('Y-m-d H:i:s'));
                    
                    // Style chart info
                    $sheet->getStyle("A{$lastRow}:A" . ($lastRow - 4))->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ]);
                }
            },
        ];
    }
}
