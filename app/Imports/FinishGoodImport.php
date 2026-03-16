<?php

namespace App\Imports;

use App\Models\Master\FinishGood;
use App\Models\Master\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class FinishGoodImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows, WithBatchInserts, WithChunkReading
{
    private int $rowCount = 0;
    private int $duplicateCount = 0;
    private int $uniqueCount = 0;
    private array $customerCache = [];

    public function __construct()
    {
        // Cache all customers at once for performance
        $this->customerCache = Customer::pluck('id', 'code')->toArray();
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $this->rowCount = $collection->count();

        foreach ($collection as $row) {
            // Cari customer_id berdasarkan customer_code
            $customerId = $this->customerCache[$row['customer_code']] ?? null;

            if (!$customerId) {
                Log::warning('FinishGood Import: Customer not found', [
                    'customer_code' => $row['customer_code'],
                    'part_number' => $row['part_number'] ?? null,
                ]);
                $this->duplicateCount++;
                continue;
            }

            // Cek duplikasi berdasarkan customer_id + part_number
            $existing = FinishGood::where('customer_id', $customerId)
                ->where('part_number', $row['part_number'])
                ->first();

            if ($existing) {
                $this->duplicateCount++;
                continue;
            }

            // Insert data baru
            try {
                FinishGood::create([
                    'customer_id' => $customerId,
                    'part_number' => $row['part_number'],
                    'part_name' => $row['part_name'],
                    'alias' => $row['alias'] ?? null,
                    'model' => $row['model'] ?? null,
                    'variant' => $row['variant'] ?? null,
                    'wh_address' => $row['wh_address'] ?? null,
                    'type' => strtoupper($row['type'] ?? 'ASSY'), // Default ASSY
                    'stock' => (int)($row['stock'] ?? 0),
                    'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
                ]);
                $this->uniqueCount++;
            } catch (\Exception $e) {
                Log::error('FinishGood Import: Failed to create record', [
                    'part_number' => $row['part_number'] ?? null,
                    'customer_code' => $row['customer_code'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $this->duplicateCount++;
            }
        }
    }

    /**
     * Validasi untuk setiap baris
     */
    public function rules(): array
    {
        return [
            'customer_code' => 'required|string',
            'part_number' => 'required|string|max:255',
            'part_name' => 'required|string|max:255',
            'type' => 'nullable|in:ASSY,DIRECT,assy,direct',
            'stock' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages()
    {
        return [
            'customer_code.required' => 'Customer code wajib diisi.',
            'part_number.required' => 'Part number wajib diisi.',
            'part_name.required' => 'Part name wajib diisi.',
            'type.in' => 'Type harus ASSY atau DIRECT.',
            'stock.integer' => 'Stock harus berupa angka.',
        ];
    }

    /**
     * Batch size untuk insert
     */
    public function batchSize(): int
    {
        return 500;
    }

    /**
     * Chunk size untuk reading
     */
    public function chunkSize(): int
    {
        return 500;
    }

    // Getter untuk summary
    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getDuplicateCount(): int
    {
        return $this->duplicateCount;
    }

    public function getUniqueCount(): int
    {
        return $this->uniqueCount;
    }
}
