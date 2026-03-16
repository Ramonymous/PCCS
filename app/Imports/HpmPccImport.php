<?php

namespace App\Imports;

use App\Models\Customer\HPM\Pcc;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class HpmPccImport implements
    OnEachRow,
    WithHeadingRow,
    SkipsEmptyRows,
    SkipsOnError,
    WithBatchInserts,
    WithChunkReading
{
    public array $summary = [
        'total' => 0,
        'unique' => 0,
        'updated' => 0,
        'skipped' => 0,
    ];

    public function onRow(Row $row): void
    {
        $data = $row->toArray();
        $this->summary['total']++;

        $slipBarcode = isset($data['slip_barcode']) ? (string) $data['slip_barcode'] : null;
        if (!$slipBarcode) {
            Log::warning('⚠️ slip_barcode kosong pada baris import PCC, baris dilewati', ['row' => $data, 'row_number' => $row->getIndex()]);
            $this->summary['skipped']++;
            return;
        }

        $dateString = $data['date'] ?? null;
        $timeString = $data['time'] ?? null;

        $convertedDate = null;
        $convertedTime = null;

        // Konversi Tanggal (Format: d-m, contoh: 04-11)
        if ($dateString) {
            try {
                $convertedDate = Carbon::createFromFormat('d-m', (string) $dateString)
                    ->year(now()->year)
                    ->toDateString();
            } catch (\Exception $e) {
                $convertedDate = null;
            }
        }

        // Konversi Waktu (Format: H:i, contoh: 08:00)
        if ($timeString) {
            try {
                $convertedTime = Carbon::createFromFormat('H:i', (string) $timeString)->toTimeString();
            } catch (\Exception $e) {
                $convertedTime = null;
            }
        }

        $payload = [
            'from'                  => $data['from'] ?? null,
            'to'                    => $data['to'] ?? null,
            'supply_address'        => $data['supply_address'] ?? null,
            'next_supply_address'   => $data['next_supply_address'] ?? null,
            'ms_id'                 => $data['ms_id'] ?? null,
            'inventory_category'    => $data['inventory_category'] ?? null,
            'part_no'               => $data['part_no'] ?? null,
            'part_name'             => $data['part_name'] ?? null,
            'color_code'            => $data['color_code'] ?? null,
            'ps_code'               => $data['ps_code'] ?? null,
            'order_class'           => $data['order_class'] ?? null,
            'prod_seq_no'           => $data['prod_seq_no'] ?? null,
            'kd_lot_no'             => $data['kd_lot_no'] ?? null,
            'ship'                  => isset($data['ship']) ? (int) $data['ship'] : null,
            'slip_no'               => $data['slip_no'] ?? null,
            'date'                  => $convertedDate,
            'time'                  => $convertedTime,
            'hns'                   => $data['hns'] ?? null,
        ];

        try {
            $model = Pcc::updateOrCreate(
                ['slip_barcode' => $slipBarcode],
                $payload + ['slip_barcode' => $slipBarcode]
            );

            if ($model->wasRecentlyCreated) {
                $this->summary['unique']++;
            } else {
                $this->summary['updated']++;
            }
        } catch (\Throwable $e) {
            Log::error('❌ Error saat import PCC', [
                'row_number' => $row->getIndex(),
                'slip_barcode' => $slipBarcode,
                'error' => $e->getMessage(),
            ]);
            $this->summary['skipped']++;
        }
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function onError(\Throwable $e): void
    {
        $this->summary['skipped']++;
        Log::error('❌ Error saat import PCC (SkipsOnError)', [
            'error' => $e->getMessage(),
        ]);
    }

    public function getSummary(): array
    {
        return $this->summary;
    }

    // Compatibility getters for existing UI summary usage
    public function getRowCount(): int
    {
        return $this->summary['total'] ?? 0;
    }

    public function getDuplicateCount(): int
    {
        // Map skipped to duplicates for legacy display
        return $this->summary['skipped'] ?? 0;
    }

    public function getUniqueCount(): int
    {
        // Treat created + updated as successfully processed rows
        return ($this->summary['unique'] ?? 0) + ($this->summary['updated'] ?? 0);
    }
}
