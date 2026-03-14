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
        // Style the header row
        $sheet->getStyle('A1:E1')->applyFromArray([
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
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
                ]);
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
