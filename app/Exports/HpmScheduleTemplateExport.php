<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class HpmScheduleTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    /**
     * Return sample data for template.
     */
    public function array(): array
    {
        return [
            [
                // Example with mixed Excel-like formats
                // slip_number | schedule_date (yyyymmdd) | adjusted_date (dd-MMM-yy) | schedule_time (HHMMSS) | adjusted_time (H:i) | delivery_time | delivery_quantity | adjustment_quantity
                '254510000592',
                '20251031',      // yyyymmdd
                '31-Oct-25',     // dd-MMM-yy
                '90000',         // HHMMSS => 09:00:00
                '13:00',         // H:i => 13:00:00
                60,
                30,
            ],
        ];
    }

    /**
     * Define column headings.
     */
    public function headings(): array
    {
        return [
            'slip_number',
            'schedule_date',
            'adjusted_date',
            'schedule_time',
            'adjusted_time',
            'delivery_quantity',
            'adjustment_quantity',
        ];
    }

    /**
     * Apply styles to worksheet.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Header row style
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ],
            // Data rows border
            'A2:H100' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]
        ];
    }

    /**
     * Define column widths.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20, // slip_number
            'B' => 18, // schedule_date
            'C' => 18, // adjusted_date
            'D' => 15, // schedule_time
            'E' => 15, // adjusted_time
            'G' => 20, // delivery_quantity
            'H' => 20, // adjustment_quantity
        ];
    }
}
