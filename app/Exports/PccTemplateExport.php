<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PccTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'from',
            'to',
            'supply_address',
            'next_supply_address',
            'ms_id',
            'inventory_category',
            'part_no',
            'part_name',
            'color_code',
            'ps_code',
            'order_class',
            'prod_seq_no',
            'kd_lot_no',
            'ship',
            'slip_no',
            'slip_barcode',
            'date',
            'time',
            'hns',
        ];
    }

    public function array(): array
    {
        return []; // empty template
    }
}