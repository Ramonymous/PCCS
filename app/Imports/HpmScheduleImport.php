<?php

namespace App\Imports;

use App\Models\Customer\HPM\Schedule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Log;

class HpmScheduleImport implements 
    ToModel, 
    WithHeadingRow, 
    WithValidation, 
    SkipsEmptyRows, 
    SkipsOnError,
    WithBatchInserts,
    WithChunkReading
{
    public array $summary = [
        'total' => 0,
        'unique' => 0,
        'updated' => 0,
        'skipped' => 0, // Track skipped rows
    ];

    /**
     * Transform each row into a Schedule model.
     */
    public function model(array $row): ?Schedule
    {
        $this->summary['total']++;

        // Convert slip_number to string to handle large numbers
        $slipNumber = isset($row['slip_number']) ? (string) $row['slip_number'] : null;

        // Safety: if slip_number missing, skip processing this row
        if (!$slipNumber) {
            Log::warning('⚠️ slip_number kosong pada baris import, baris dilewati', ['row' => $row]);
            $this->summary['skipped']++;
            return null;
        }

        $payload = [
            'schedule_date' => $this->parseDate($row['schedule_date'] ?? null),
            'adjusted_date' => $this->parseDate($row['adjusted_date'] ?? null),
            'schedule_time' => $this->parseTime($row['schedule_time'] ?? null),
            'adjusted_time' => $this->parseTime($row['adjusted_time'] ?? null),
            'delivery_quantity' => isset($row['delivery_quantity']) ? (int) $row['delivery_quantity'] : 0,
            'adjustment_quantity' => isset($row['adjustment_quantity']) ? (int) $row['adjustment_quantity'] : 0,
        ];

        Log::info('📝 Processing row', [
            'slip_number' => $slipNumber,
            'payload' => $payload
        ]);

        // Upsert by slip_number to ensure Eloquent events (including HasUlid creating) fire
        $model = Schedule::updateOrCreate(
            ['slip_number' => $slipNumber],
            $payload
        );

        if ($model->wasRecentlyCreated) {
            $this->summary['unique']++;
            Log::info('✅ Schedule dibuat dari import', ['slip_number' => $slipNumber, 'id' => $model->id]);
        } else {
            $this->summary['updated']++;
            Log::info('♻️ Schedule diupdate dari import', ['slip_number' => $slipNumber, 'id' => $model->id]);
        }

        // Return null because we've already persisted changes
        return null;
    }

    /**
     * Validation rules for each row.
     */
    public function rules(): array
    {
        return [
            // Change to string/numeric to handle large numbers
            'slip_number' => 'required|numeric',
            'schedule_date' => 'nullable',
            'adjusted_date' => 'nullable',
            'schedule_time' => 'nullable',
            'adjusted_time' => 'nullable',
            'delivery_quantity' => 'nullable|numeric',
            'adjustment_quantity' => 'nullable|numeric',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function customValidationMessages(): array
    {
        return [
            'slip_number.required' => 'Slip number is required',
            'slip_number.numeric' => 'Slip number must be numeric',
        ];
    }

    /**
     * Handle errors during import.
     */
    public function onError(\Throwable $e): void
    {
        $this->summary['skipped']++;
        Log::error("❌ Error saat import Schedule", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Batch size for bulk insert.
     */
    public function batchSize(): int
    {
        return 500;
    }

    /**
     * Chunk size for reading large files.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $str = trim((string) $value);

            // Pattern: yyyymmdd (e.g., 20251031)
            if (preg_match('/^\d{8}$/', $str)) {
                $y = substr($str, 0, 4);
                $m = substr($str, 4, 2);
                $d = substr($str, 6, 2);
                $date = \Carbon\Carbon::createFromFormat('Y-m-d', "$y-$m-$d");
                Log::debug('📅 Parsed yyyymmdd date', ['input' => $str, 'output' => $date->format('Y-m-d')]);
                return $date->format('Y-m-d');
            }

            // Pattern: dd-MMM-yy or dd-MMM-YYYY (e.g., 31-Oct-25)
            if (preg_match('/^\d{1,2}-[A-Za-z]{3}-\d{2,4}$/', $str)) {
                // Try two-digit year first, then four-digit
                try {
                    $date = \Carbon\Carbon::createFromFormat('d-M-y', $str);
                    Log::debug('📅 Parsed dd-MMM-yy date', ['input' => $str, 'output' => $date->format('Y-m-d')]);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    $date = \Carbon\Carbon::createFromFormat('d-M-Y', $str);
                    Log::debug('📅 Parsed dd-MMM-YYYY date', ['input' => $str, 'output' => $date->format('Y-m-d')]);
                    return $date->format('Y-m-d');
                }
            }

            // Excel serial date (numeric with reasonable range or decimal)
            if (is_numeric($str) && (float)$str > 1000) {
                // If it's a reasonable Excel serial number
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$str);
                Log::debug('📅 Parsed Excel serial date', ['input' => $str, 'output' => $date->format('Y-m-d')]);
                return $date->format('Y-m-d');
            }

            // Fallback to Carbon parser
            $date = \Carbon\Carbon::parse($str);
            Log::debug('📅 Parsed generic date', ['input' => $str, 'output' => $date->format('Y-m-d')]);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("⚠️ Gagal parse date", ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse time from various formats.
     */
    private function parseTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $str = trim((string) $value);

            // If Excel time fraction like 0.5 or 0.375
            if (is_numeric($str) && strpos($str, '.') !== false && (float)$str < 1) {
                $time = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$str);
                Log::debug('⏰ Parsed Excel time fraction', ['input' => $str, 'output' => $time->format('H:i:s')]);
                return $time->format('H:i:s');
            }

            // Numeric HHMMSS/HMMSS/HHMM formats (e.g., 90000 => 09:00:00, 13000 => 13:00:00)
            if (preg_match('/^\d{1,6}$/', $str)) {
                // Pad to 6 digits if needed
                if (strlen($str) <= 4) {
                    // Format: HHMM or HMM
                    $padded = str_pad($str, 4, '0', STR_PAD_LEFT) . '00';
                } else {
                    // Format: HHMMSS or HMMSS
                    $padded = str_pad($str, 6, '0', STR_PAD_LEFT);
                }
                
                $h = (int) substr($padded, 0, 2);
                $m = (int) substr($padded, 2, 2);
                $s = (int) substr($padded, 4, 2);
                $h = $h % 24; // normalize
                $result = sprintf('%02d:%02d:%02d', $h, $m, $s);
                Log::debug('⏰ Parsed numeric time', ['input' => $str, 'padded' => $padded, 'output' => $result]);
                return $result;
            }

            // Formats like H:i or H:i:s
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str)) {
                $result = strlen($str) === 5 ? $str . ':00' : $str;
                Log::debug('⏰ Parsed H:i time', ['input' => $str, 'output' => $result]);
                return $result;
            }

            // Fallback to Carbon parser
            $time = \Carbon\Carbon::parse($str);
            Log::debug('⏰ Parsed generic time', ['input' => $str, 'output' => $time->format('H:i:s')]);
            return $time->format('H:i:s');
        } catch (\Exception $e) {
            Log::warning("⚠️ Gagal parse time", ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get import summary.
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}