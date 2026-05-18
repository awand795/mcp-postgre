<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $users;
    protected $isTemplate;

    public function __construct($users = null, $isTemplate = false)
    {
        $this->users = $users ?? User::with('roleModel')->get();
        $this->isTemplate = $isTemplate;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->isTemplate) {
            // Return empty collection for template
            return collect([]);
        }

        return $this->users->map(function ($user) {
            return collect([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_name' => $user->roleModel->name ?? 'No Role',
                'is_admin' => $user->is_admin ? 'Yes' : 'No',
                'created_at' => $user->created_at,
            ]);
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        if ($this->isTemplate) {
            // Template headings for import
            return [
                'Name',
                'Email',
                'Password',
                'Role (ID or Name)',
                'Is Admin (Yes/No)',
            ];
        }

        // Export headings
        return [
            'ID',
            'Name',
            'Email',
            'Role',
            'Is Admin',
            'Created At',
        ];
    }

    /**
     * @param mixed $row
     *
     * @return array
     */
    public function map($row): array
    {
        if ($this->isTemplate) {
            return [];
        }

        return [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['role_name'],
            $row['is_admin'],
            $row['created_at'],
        ];
    }

    /**
     * @param Worksheet $sheet
     *
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Style the header row (export=6 kolom A-F, template=5 kolom A-E)
        $headerRange = $this->isTemplate ? 'A1:E1' : 'A1:F1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Explicitly set header row height
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Auto-size columns (export: A-F, template: A-E)
        $lastCol = $this->isTemplate ? 'E' : 'F';
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            
            // Force calculate width so we can add padding
            $sheet->getParent()->getActiveSheet()->calculateColumnWidths();
            $currentWidth = $sheet->getColumnDimension($col)->getWidth();
            
            if ($currentWidth < 12) $currentWidth = 12;
            
            $sheet->getColumnDimension($col)->setAutoSize(false);
            $sheet->getColumnDimension($col)->setWidth($currentWidth + 8); // Added generous padding
            
            // Add indentation for aesthetics
            $sheet->getStyle($col . '1:' . $col . $sheet->getHighestRow())
                ->getAlignment()
                ->setIndent(2);
        }

        // Add example data for template
        if ($this->isTemplate) {
            $sheet->fromArray([
                ['John Doe', 'john@example.com', 'password123', '1', 'No'],
                ['Jane Smith', 'jane@example.com', 'password123', 'Admin', 'Yes'],
            ], null, 'A2');

            // Add instruction row
            $sheet->mergeCells('A7:E7');
            $sheet->setCellValue('A7', 'Instructions:');
            $sheet->getStyle('A7')->applyFromArray([
                'font' => ['bold' => true],
            ]);

            $instructions = [
                'A8' => '1. Fill in Name (required)',
                'A9' => '2. Fill in Email (required, must be unique)',
                'A10' => '3. Fill in Password (optional, defaults to "password123" if empty)',
                'A11' => '4. Fill in Role (optional, can be Role ID or Role Name)',
                'A12' => '5. Fill in Is Admin (optional, use Yes/No or 1/0)',
                'A14' => 'Note: Do not modify the header row (row 1).',
            ];

            foreach ($instructions as $cell => $text) {
                $sheet->setCellValue($cell, $text);
            }

            // Style instruction rows
            $sheet->getStyle('A8:A14')->applyFromArray([
                'font' => ['size' => 10],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
            ]);
        } else {
            // Style data rows for export
            $highestRow = $sheet->getHighestRow();
            if ($highestRow > 1) {
                $sheet->getStyle('A2:F' . $highestRow)->applyFromArray([
                    'alignment' => [
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'EEEEEE'],
                        ],
                    ],
                ]);
                
                // Set uniform row height for data
                for ($i = 2; $i <= $highestRow; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(25);
                }
            }
        }
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->isTemplate ? 'Template' : 'Users';
    }
}
