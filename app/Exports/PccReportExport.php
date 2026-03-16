<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Database\Query\Builder;

class PccReportExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    public function __construct(
        protected string $fromDate,
        protected ?string $toDate = null,
    ) {
        if (!$this->toDate) {
            $this->toDate = $this->fromDate;
        }
    }

    public function query()
    {
        $effectiveDateExpr = DB::raw('COALESCE(s.adjusted_date, s.schedule_date, p.date)');

        // Correlated subselect builders (reuse like in the view query)
        $latestEventTs = function (string $type) {
            return DB::table('hpm_pcc_events as e')
                ->select('e.event_timestamp')
                ->join('hpm_pcc_traces as t', 't.id', '=', 'e.pcc_trace_id')
                ->whereColumn('t.pcc_id', 'p.id')
                ->where('e.event_type', $type)
                ->orderByDesc('e.event_timestamp')
                ->limit(1);
        };

        $latestEventUser = function (string $type) {
            return DB::table('hpm_pcc_events as e')
                ->select('u.name')
                ->join('hpm_pcc_traces as t', 't.id', '=', 'e.pcc_trace_id')
                ->leftJoin('users as u', 'u.id', '=', 'e.event_users')
                ->whereColumn('t.pcc_id', 'p.id')
                ->where('e.event_type', $type)
                ->orderByDesc('e.event_timestamp')
                ->limit(1);
        };

        return DB::table('pccs as p')
            ->leftJoin('hpm_schedules as s', 's.slip_number', '=', 'p.slip_no')
            ->leftJoin('finish_goods as fg', 'fg.part_number', '=', 'p.part_no')
            ->whereBetween($effectiveDateExpr, [$this->fromDate, $this->toDate])
            ->select([
                'p.slip_barcode as barcode',
                DB::raw('COALESCE(fg.part_number, p.part_no) as fg_part_number'),
                DB::raw('COALESCE(fg.part_name, p.part_name) as fg_part_name'),
            ])
            ->selectSub($latestEventTs('PRODUCTION CHECK'), 'production_checked_at')
            ->selectSub($latestEventUser('PRODUCTION CHECK'), 'production_by')
            ->selectSub($latestEventTs('RECEIVED'), 'received_at')
            ->selectSub($latestEventUser('RECEIVED'), 'received_by')
            ->selectSub($latestEventTs('PDI CHECK'), 'pdi_checked_at')
            ->selectSub($latestEventUser('PDI CHECK'), 'pdi_by')
            ->selectSub($latestEventTs('DELIVERY'), 'delivery_at')
            ->selectSub($latestEventUser('DELIVERY'), 'delivery_by')
            ->orderBy('p.slip_barcode');
    }

    public function headings(): array
    {
        return [
            'Slip Barcode',
            'Finish Good Part Number',
            'Finish Good Part Name',
            'Production Checked At',
            'Production By',
            'Received At',
            'Received By',
            'PDI Checked At',
            'PDI By',
            'Delivery At',
            'Delivery By',
        ];
    }

    public function map($row): array
    {
        $toCarbon = fn($v) => $v ? \Carbon\Carbon::parse($v) : null;
        return [
            $row->barcode,
            $row->fg_part_number,
            $row->fg_part_name,
            $toCarbon($row->production_checked_at),
            $row->production_by,
            $toCarbon($row->received_at),
            $row->received_by,
            $toCarbon($row->pdi_checked_at),
            $row->pdi_by,
            $toCarbon($row->delivery_at),
            $row->delivery_by,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Freeze header
        $sheet->freezePane('A2');
        // Bold header and light fill
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEAEAEA');

        return [];
    }

    public function columnFormats(): array
    {
        // D, F, H, J are datetime columns
        return [
            'D' => NumberFormat::FORMAT_DATE_DATETIME,
            'F' => NumberFormat::FORMAT_DATE_DATETIME,
            'H' => NumberFormat::FORMAT_DATE_DATETIME,
            'J' => NumberFormat::FORMAT_DATE_DATETIME,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Auto filter on header row
                $event->sheet->setAutoFilter('A1:K1');
            },
        ];
    }
}
