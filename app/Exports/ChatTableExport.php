<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChatTableExport implements FromArray, WithHeadings, WithStyles, WithTitle
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
        // Simple header styling only
        $lastCol = $sheet->getHighestColumn();
        
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F53003']],
        ]);
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }
}
