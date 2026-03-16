<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class FinishGoodTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    /**
     * Return template data with examples
     */
    public function array(): array
    {
        return [
            [
                'HPM',           // customer_code
                'ABC123',        // part_number
                'FRONT BUMPER',  // part_name
                'FB-01',         // alias
                'BRIO',          // model
                'RS',            // variant
                'A1-B2',         // wh_address
                'ASSY',          // type (ASSY or DIRECT)
                '0',             // stock
                '1',             // is_active (1 = active, 0 = inactive)
            ],
            [
                'HPM',
                'XYZ789',
                'REAR BUMPER',
                'RB-01',
                'CIVIC',
                'Type R',
                'C3-D4',
                'DIRECT',
                '100',
                '1',
            ],
        ];
    }

    /**
     * Return column headers
     */
    public function headings(): array
    {
        return [
            'customer_code',    // Required: Kode customer (e.g., HPM, TMI)
            'part_number',      // Required: Nomor part
            'part_name',        // Required: Nama part
            'alias',            // Optional: Alias/nama alternatif
            'model',            // Optional: Model kendaraan
            'variant',          // Optional: Varian model
            'wh_address',       // Optional: Alamat gudang
            'type',             // Required: ASSY atau DIRECT
            'stock',            // Optional: Stock awal (default 0)
            'is_active',        // Optional: 1 = aktif, 0 = nonaktif (default 1)
        ];
    }

    /**
     * Style the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style for header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Style for data rows
            2 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'E7E6E6'],
                ],
            ],
            3 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'E7E6E6'],
                ],
            ],
        ];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18, // customer_code
            'B' => 20, // part_number
            'C' => 30, // part_name
            'D' => 15, // alias
            'E' => 15, // model
            'F' => 15, // variant
            'G' => 15, // wh_address
            'H' => 12, // type
            'I' => 12, // stock
            'J' => 12, // is_active
        ];
    }
}
