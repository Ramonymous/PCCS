<?php

use Livewire\Volt\Component;
use App\Models\Customer\HPM\Pcc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HpmPccImport;
use App\Exports\PccTemplateExport;
use App\Jobs\PrintLabelsPCC;

new
#[Title('Data Management')]
class extends Component {
    use WithPagination, WithFileUploads, Toast;

    /* ----------
    | Properti Filter & Penyortiran
    |-------------------------------------------------------------------------- */
    public string $searchPartNo        = '';
    public string $searchSlipBarcode   = '';
    public string $searchLotBarcode    = '';
    public string $searchDateStart;
    public string $searchDateEnd;
    public array  $sortBy              = ['column' => 'effective_date', 'direction' => 'asc'];
    public int    $perPage             = 100;

    /* ----------
    | Properti Seleksi Data
    |-------------------------------------------------------------------------- */
    public array  $selectedIds         = [];

    /* ----------
    | Properti Modal Import
    |-------------------------------------------------------------------------- */
    public bool   $showImportModal     = false;
    #[Validate('nullable|file|mimes:xlsx,xls|max:5120')]
    public        $importFile          = null;
    public array $summary = ['total' => 0, 'duplicates' => 0, 'unique' => 0];
    public array  $importData          = [];

    /* ----------
    | Properti Modal Detail
    |-------------------------------------------------------------------------- */
    public bool   $detailsModal        = false;
    public        $selectedData        = null;

    /**
     * Inisialisasi komponen, atur tanggal default.
     */
    public function mount()
    {
        $this->searchDateStart = now()->startOfMonth()->format('Y-m-d');
        $this->searchDateEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    /* ----------
    | Computed Property (Cached)
    |-------------------------------------------------------------------------- */

    /**
     * Menghitung jumlah item yang dipilih.
     * Cached untuk performa.
     */
    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedIds);
    }

    /**
     * Headers tabel (cached - static data).
     * Using Redis tags for efficient cache invalidation.
     */
    #[Computed]
    public function headers(): array
    {
        return Cache::tags(['pcc_ui'])->remember('pcc_table_headers', 3600, function() {
            return [
                ['key' => 'kd_lot_no',      'label' => __('KD Lot No'),    'sortable' => true],
                ['key' => 'part_no',        'label' => __('Part No'),      'sortable' => true],
                ['key' => 'slip_barcode',   'label' => __('Slip Barcode'), 'sortable' => true],
                ['key' => 'effective_date', 'label' => __('Eff. Date'),    'sortable' => true],
                ['key' => 'effective_time', 'label' => __('Eff. Time'),    'sortable' => true],
                ['key' => 'actions',        'label' => __('Actions'),      'sortable' => false],
            ];
        });
    }

    /* ----------
    | Data & Query
    |-------------------------------------------------------------------------- */

    /**
     * Menyiapkan data untuk tabel.
     * Don't cache paginated data - Octane + pagination doesn't mix well with caching.
     */
    public function with(): array
    {
        return [
            'datas'   => $this->dataQuery()->paginate($this->perPage),
            'headers' => $this->headers,
            'selectedCount' => $this->selectedCount,
        ];
    }



    /**
     * Membangun query utama untuk mengambil data PCC.
     * Returns base query - pagination happens in with() method.
     */
    public function dataQuery()
    {
        $query = Pcc::query()
            ->with([
                'schedule:id,slip_number,schedule_date,adjusted_date,schedule_time,adjusted_time',
                'finishGood:id,part_number,part_name,alias,model,variant'
            ])
            ->leftJoin('hpm_schedules', 'pccs.slip_no', '=', 'hpm_schedules.slip_number')
            ->select('pccs.*',
                DB::raw('COALESCE(hpm_schedules.adjusted_date, hpm_schedules.schedule_date, pccs.date) as effective_date_alias'),
                DB::raw('COALESCE(hpm_schedules.adjusted_time, hpm_schedules.schedule_time, pccs.time) as effective_time_alias')
            )
            ->when($this->searchLotBarcode,  fn($q) => $q->where('pccs.kd_lot_no',   'like', "%{$this->searchLotBarcode}%"))
            ->when($this->searchPartNo,      fn($q) => $q->where('pccs.part_no',     'like', "%{$this->searchPartNo}%"))
            ->when($this->searchSlipBarcode, fn($q) => $q->where('pccs.slip_barcode','like', "%{$this->searchSlipBarcode}%"))
            ->when($this->searchDateStart && $this->searchDateEnd,
                fn($q) => $q->whereBetween(
                    DB::raw('COALESCE(hpm_schedules.adjusted_date, hpm_schedules.schedule_date, pccs.date)'),
                    [$this->searchDateStart, $this->searchDateEnd]
                ));

        $sortColumn = $this->sortBy['column'];
        $sortDirection = $this->sortBy['direction'];

        return match ($sortColumn) {
            'effective_date' => $query->orderBy('effective_date_alias', $sortDirection),
            'effective_time' => $query->orderBy('effective_time_alias', $sortDirection),
            default => $query->orderBy("pccs.{$sortColumn}", $sortDirection),
        };
    }

    /* ----------
    | Aksi CRUD & UI
    |-------------------------------------------------------------------------- */

    /**
     * Menampilkan detail item dalam modal dengan caching.
     * Using Redis tags for targeted cache invalidation.
     */
    public function viewDetails(string $id): void
    {
        try {
            // Cache detail data untuk 10 menit dengan tags
            $this->selectedData = Cache::tags(['pcc_details', "pcc_{$id}"])->remember("pcc_detail_{$id}", 600, function() use ($id) {
                return Pcc::with(['schedule', 'finishGood'])->find($id);
            });

            if (!$this->selectedData) {
                $this->error(__('Data not found.'), null, 'toast-top toast-end');
                return;
            }
            $this->detailsModal = true;
            
        } catch (\Exception $e) {
            Log::error('View PCC Details Error', [
                'user_id' => auth()->id(),
                'pcc_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->error(__('An error occurred while loading details. Please refresh the page and try again.'), null, 'toast-top toast-end');
        }
    }

    /**
     * Menutup modal detail.
     */
    public function closeDetailsModal(): void
    {
        $this->detailsModal = false;
        $this->selectedData = null;
    }

    /**
     * Menghapus data PCC dan invalidate cache.
     * Using Redis tags for efficient cache invalidation.
     */
    public function delete(string $id): void
    {
        $data = Pcc::find($id);
        if (!$data) {
            $this->error(__('Data not found.'), null, 'toast-top toast-end');
            return;
        }
        
        $data->delete();
        
        // Invalidate specific item cache and query caches using tags
        Cache::tags(["pcc_{$id}"])->flush();
        $this->clearQueryCache();
        
        $this->success(__('Data deleted successfully.'), null, 'toast-top toast-end');
        $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));

        if ($this->selectedData && $this->selectedData->id == $id) {
            $this->closeDetailsModal();
        }
        $this->resetPage();
    }

    /**
     * Membersihkan semua filter pencarian dan cache.
     */
    public function clearFilters(): void
    {
        $this->reset(['searchPartNo','searchSlipBarcode','searchLotBarcode']);
        $this->searchDateStart = now()->startOfMonth()->format('Y-m-d');
        $this->searchDateEnd   = now()->endOfMonth()->format('Y-m-d');
        $this->clearQueryCache();
        $this->resetPage();
    }

    /**
     * Helper untuk clear query cache.
     * Using Redis tags for efficient selective cache invalidation.
     */
    private function clearQueryCache(): void
    {
        // Clear only PCC-related query caches using tags
        Cache::tags(['pcc_queries'])->flush();
    }

    /* ----------
    | Lifecycle Hooks
    |-------------------------------------------------------------------------- */

    /**
     * Dipanggil saat properti publik diperbarui.
     */
    public function updated($property): void
    {
        // Jika properti filter berubah, reset paginasi dan clear cache
        if (in_array($property, [
            'searchPartNo','searchSlipBarcode','searchLotBarcode',
            'searchDateStart','searchDateEnd','perPage','sortBy'
        ])) {
            $this->clearQueryCache();
            $this->resetPage();
        }
        // Validasi file import saat di-upload
        if ($property === 'importFile') {
            $this->validateOnly('importFile');
        }
    }

    /* ----------
    | Fungsionalitas Import
    |-------------------------------------------------------------------------- */

    /**
     * Membuka modal import.
     */
    public function openImportModal(): void
    {
        $this->reset(['importFile','summary','importData']);
        $this->showImportModal = true;
    }

    /**
     * Menutup modal import.
     */
    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile','summary','importData']);
    }

    /**
     * Mengunduh template Excel untuk import.
     */
    public function downloadTemplate()
    {
        try {
            return Excel::download(new PccTemplateExport, 'pcc_import_template.xlsx', \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error('PCC Template Download Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred while downloading template. Please try again.'), null, 'toast-top toast-end');
        }
    }

    /**
     * Memproses file Excel yang di-upload.
     */
    public function importPccs(): void
    {
        $this->validateOnly('importFile');
        if (!$this->importFile) {
            $this->error(__('Import file not found.'), null, 'toast-top toast-end');
            return;
        }

        try {
            // Simpan file sementara ke disk 'local' dalam folder imports_temp
            $disk = 'local';
            Storage::disk($disk)->makeDirectory('imports_temp');
            $tmpPath = $this->importFile->store('imports_temp', $disk);
            $import  = new HpmPccImport;

            // Proses import menggunakan path relatif + disk agar lintas environment aman
            Excel::import($import, $tmpPath, $disk);

            // Ambil ringkasan dari kelas import
            $this->summary = [
                'total'      => method_exists($import,'getRowCount')      ? $import->getRowCount()      : 0,
                'duplicates' => method_exists($import,'getDuplicateCount')? $import->getDuplicateCount(): 0,
                'unique'     => method_exists($import,'getUniqueCount')   ? $import->getUniqueCount()   : 0,
            ];

            if ($this->summary['unique'] > 0) {
                $this->success(__('Import Complete! :count unique data successfully saved.', ['count' => $this->summary['unique']]), null, 'toast-top toast-end');
            } else {
                $this->warning(__('Empty file or no valid new data to import.'), null, 'toast-top toast-end');
            }

            // Hapus file sementara dari disk yang sama
            Storage::disk($disk)->delete($tmpPath);
            
            // Invalidate all PCC-related caches after import
            $this->clearQueryCache();
            Cache::tags(['pcc_details'])->flush(); // Clear all detail caches
            
            $this->closeImportModal();
            $this->resetPage();

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Tangani error validasi dari Maatwebsite/Excel
            $failures = $e->failures();
            $first    = $failures[0] ?? null;
            
            // Log detailed validation errors
            Log::warning('PCC Import Validation Failed', [
                'user_id' => auth()->id(),
                'failures_count' => count($failures),
                'first_error' => $first ? [
                    'row' => $first->row(),
                    'attribute' => $first->attribute(),
                    'errors' => $first->errors(),
                ] : null,
            ]);
            
            // Show specific validation error to user (this is helpful, not a security risk)
            $msg = $first ? __('Error on row :row: :error', ['row' => $first->row(), 'error' => $first->errors()[0]]) : __('File format mismatch');
            $this->error(__('Validation Failed: :msg', ['msg' => $msg]), null, 'toast-top toast-end');

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error('PCC Import Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred during import process. Please check file format and try again.'), null, 'toast-top toast-end');
        }
    }

    /* ----------
    | Fungsionalitas Print (Job Dispatch)
    |-------------------------------------------------------------------------- */

    /**
     * Memulai proses 'bulk print' untuk item yang dipilih.
     * Method ini ada di class Livewire Component
     */
    public function bulkPrint(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning(__('Select at least one item to print'), null, 'toast-top toast-end');
            return;
        }

        $user = auth()->user();
        if (!$user) {
            $this->error(__('User not authenticated.'), null, 'toast-top toast-end');
            return;
        }

        // 1. Ambil state 'selectedIds' dan bersihkan
        $idsToProcess = array_values(array_filter($this->selectedIds, function($id) {
            return is_string($id) && !empty(trim($id));
        }));
        
        $totalSelected = count($idsToProcess);

        Log::info("📋 Bulk Print Dimulai", [
            'user_id' => $user->id,
            'total_selected' => $totalSelected,
            'selected_ids_sample' => array_slice($idsToProcess, 0, 5) // Log sample saja untuk performa
        ]);

        if (empty($idsToProcess)) {
            $this->error(__('No valid IDs selected.'), null, 'toast-top toast-end');
            return;
        }

        // 2. ✅ CRITICAL FIX: Validasi ID dengan cara yang lebih efisien
        $validIds = Pcc::whereIn('id', $idsToProcess)
            ->pluck('id')
            ->toArray();
        
        // Reset array keys untuk memastikan sequential
        $validIds = array_values($validIds);
        $validCount = count($validIds);
        $invalidCount = $totalSelected - $validCount;

        Log::info("✅ Validasi ID selesai", [
            'total_selected' => $totalSelected,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount
        ]);

        if (empty($validIds)) {
            $this->error(__('No valid data selected to print.'), null, 'toast-top toast-end');
            Log::warning("⚠️ Tidak ada ID valid ditemukan untuk dicetak", [
                'selected_ids_sample' => array_slice($idsToProcess, 0, 10),
                'user_id' => $user->id
            ]);
            return;
        }

        // 3. Dispatch job HANYA dengan ID yang valid
        try {
            PrintLabelsPCC::dispatch($validIds, $user);

            Log::info("✅ Job print berhasil didispatch", [
                'user_id' => $user->id,
                'valid_ids_count' => $validCount
            ]);

            // 4. Berikan feedback ke user
            if ($invalidCount > 0) {
                $this->success(
                    __('Print job sent for :count items (from :total selected). :invalid invalid items ignored.', [
                        'count' => $validCount,
                        'total' => $totalSelected,
                        'invalid' => $invalidCount
                    ]),
                    null,
                    'toast-top toast-end'
                );
                Log::warning("⚠️ Beberapa ID tidak valid saat print", [
                    'invalid_count' => $invalidCount,
                    'valid_count' => $validCount
                ]);
            } else {
                $this->success(
                    __('Print job sent for :count items. Waiting for results...', ['count' => $validCount]),
                    null,
                    'toast-top toast-end'
                );
            }

            // 5. Kirim event untuk menampilkan modal 'processing'
            $this->dispatch('print-job-started');

            // 6. Reset seleksi SETELAH job berhasil dikirim
            $this->selectedIds = [];

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error("❌ Gagal dispatch print job", [
                'user_id' => $user->id,
                'valid_ids_count' => $validCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Show generic error to user
            $this->error(__('An error occurred while processing print job. Please try again.'), null, 'toast-top toast-end');
        }
    }
}
?>

<div>
    {{-- HEADER --}}
    <x-header :title="__('Data Management')" :subtitle="__('List of PCC Records')" separator progress-indicator>
        <x-slot:actions>
            <x-button
                :label="__('Import Data')"
                icon="o-arrow-up-tray"
                wire:click="openImportModal"
                class="btn-primary"
                responsive />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS --}}
    <x-card :title="__('Search Filters')" :subtitle="__('Filter records')" class="mb-4" shadow separator>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <x-input :label="__('Part No')" wire:model.live.debounce.300ms="searchPartNo" placeholder="Filter by part no" icon="o-magnifying-glass" clearable />
            <x-input :label="__('Slip Barcode')" wire:model.live.debounce.300ms="searchSlipBarcode" placeholder="Filter by slip barcode" icon="o-magnifying-glass" clearable />
            <x-input :label="__('KD Lot No')" wire:model.live.debounce.300ms="searchLotBarcode" placeholder="Filter by KD Lot No" icon="o-magnifying-glass" clearable />
            <x-input :label="__('Date From')" wire:model.live.debounce.300ms="searchDateStart" type="date" icon="o-calendar" />
            <x-input :label="__('Date To')" wire:model.live.debounce.300ms="searchDateEnd" type="date" icon="o-calendar" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Clear Filters')" wire:click="clearFilters" icon="o-x-mark" spinner="clearFilters" class="btn-ghost" />
        </x-slot:actions>
    </x-card>

    {{-- TABLE --}}
    <x-card :title="__('Data Records')" shadow separator>
        {{-- Indikator loading untuk 'selectedIds' atau 'bulkPrint' --}}
        <x-slot:menu class="flex items-center gap-2">
            <div wire:loading wire:target="selectedIds" class="text-sm text-gray-500">
                {{ __('Updating...') }}
            </div>
            @if($selectedCount > 0)
                <x-button
                    :label="__('Print Selected')"
                    icon="o-printer"
                    wire:click="bulkPrint"
                    class="btn-success"
                    spinner="bulkPrint"
                    :badge="$selectedCount"
                    badge-classes="badge-warning"
                    responsive />
            @endif
        </x-slot:menu>

           <x-table :headers="$headers" :rows="$datas" wire:model.live="selectedIds"
               selectable with-pagination selectable-key="id" per-page="perPage" :per-page-values="[50, 100, 150, 200, 250]" :sort-by="$sortBy">
            
            {{-- Format Tampilan Kolom Part No dengan Alias --}}
            @scope('cell_part_no', $data)
                <div>
                    @if($data->finishGood && $data->finishGood->part_number)
                        <p class="font-semibold">{{ $data->finishGood->part_number }}</p>
                        @if($data->finishGood->part_name)
                            <p class="text-xs text-blue-500">{{ $data->finishGood->part_name }}</p>
                        @endif
                    @else
                        <p class="font-semibold">{{ $data->part_no }}</p>
                        <p class="text-xs text-red-500">{{ __('No FinishGood data') }}</p>
                    @endif
                </div>
            @endscope

            {{-- Format Tampilan Kolom Tanggal Efektif --}}
            @scope('cell_effective_date', $data)
                {{ $data->effective_date ? \Carbon\Carbon::parse($data->effective_date)->format('d/m/Y') : '-' }}
            @endscope

            {{-- Format Tampilan Kolom Waktu Efektif --}}
            @scope('cell_effective_time', $data)
                {{ $data->effective_time ? substr($data->effective_time, 0, 5) : '-' }}
            @endscope

            {{-- Kolom Aksi (View, Delete) --}}
            @scope('actions', $data)
                <div class="flex gap-1">
                    <x-button icon="o-eye" class="btn-ghost btn-sm text-info" wire:click="viewDetails('{{ $data->id }}')" :tooltip-left="__('View Details')" />
                    <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                              wire:click="delete('{{ $data->id }}')"
                              wire:confirm.prompt="{{ __('Are you sure?') }}\n{{ __('Type DELETE to confirm.') }}|DELETE"
                              :tooltip-left="__('Delete')" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- IMPORT MODAL --}}
    <x-modal wire:model="showImportModal" :title="__('Import PCC Data')" class="backdrop-blur" persistent separator>
        <div class="space-y-6">
            <x-alert :title="__('Warning')" icon="o-exclamation-triangle" class="alert-warning">
                <p>{{ __('Make sure your Excel file uses the correct template. Required columns must be filled.') }}</p>
            </x-alert>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Kolom Download Template --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-primary badge-lg">1</span>
                        <h3 class="font-semibold text-lg">{{ __('Download Template') }}</h3>
                    </div>
                    <p class="text-sm text-base-content/70">{{ __('Download official Excel template to ensure your data format is correct.') }}</p>
                    <x-button :label="__('Download Template')" icon="o-arrow-down-tray" wire:click="downloadTemplate" spinner="downloadTemplate" class="btn-info" />
                </div>
                {{-- Kolom Upload Data --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-primary badge-lg">2</span>
                        <h3 class="font-semibold text-lg">{{ __('Upload Your Data') }}</h3>
                    </div>
                    <p class="text-sm text-base-content/70">{{ __('Upload Excel file (.xlsx, .xls) that has been filled in here.') }}</p>
                    <x-file wire:model="importFile" accept=".xlsx,.xls" :hint="__('Maximum 5MB, format .xlsx or .xls')" />
                    @error('importFile')
                        <x-alert :title="__('Validation Error')" description="{{ $message }}" icon="o-exclamation-triangle" class="alert-error mt-3" />
                    @enderror
                </div>
            </div>
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="closeImportModal" class="btn-ghost" />
            <x-button :label="__('Import Data')" class="btn-primary" wire:click="importPccs" spinner="importPccs" :disabled="!$importFile" />
        </x-slot:actions>
    </x-modal>

    {{-- DETAIL MODAL --}}
    <x-modal wire:model="detailsModal" :title="__('Detail Record PCC')" class="backdrop-blur" separator wire:key="detail-modal-{{ $selectedData?->id ?? 'empty' }}">
        @if ($selectedData)
            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                {{-- Bagian Utama --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="text-xs text-base-content/70">{{ __('KD Lot No') }}</label><p class="font-semibold">{{ $selectedData->kd_lot_no }}</p></div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Part No') }}</label>
                        <p class="font-semibold">{{ $selectedData->finishGood->part_number }}</p>
                        @if($selectedData->finishGood)
                            @if($selectedData->finishGood->part_name)
                                <p class="text-xs text-gray-500 mt-1">{{ $selectedData->finishGood->part_name }}</p>
                            @endif
                            @if($selectedData->finishGood->model || $selectedData->finishGood->variant)
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $selectedData->finishGood->model }}
                                    @if($selectedData->finishGood->variant) / {{ $selectedData->finishGood->variant }} @endif
                                </p>
                            @endif
                        @endif
                    </div>
                    <div><label class="text-xs text-base-content/70">{{ __('Part Name') }}</label><p class="font-semibold">{{ $selectedData->part_name }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Slip Barcode') }}</label><p class="font-semibold">{{ $selectedData->slip_barcode }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Ship Quantity') }}</label><p class="font-semibold">{{ $selectedData->ship }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Date') }}</label><p class="font-semibold">{{ $selectedData->date?->format('d/m/Y') ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Time') }}</label><p class="font-semibold">{{ $selectedData->effective_time ? substr($selectedData->effective_time, 0, 5) : '-' }}</p></div>
                </div>

                <x-hr />

                {{-- Bagian Tambahan --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="text-xs text-base-content/70">{{ __('From') }}</label><p class="font-semibold">{{ $selectedData->from ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('To') }}</label><p class="font-semibold">{{ $selectedData->to ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Supply Address') }}</label><p class="font-semibold">{{ $selectedData->supply_address ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Next Supply Address') }}</label><p class="font-semibold">{{ $selectedData->next_supply_address ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('MS ID') }}</label><p class="font-semibold">{{ $selectedData->ms_id ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Inventory Category') }}</label><p class="font-semibold">{{ $selectedData->inventory_category ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Color Code') }}</label><p class="font-semibold">{{ $selectedData->color_code ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('PS Code') }}</label><p class="font-semibold">{{ $selectedData->ps_code ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Order Class') }}</label><p class="font-semibold">{{ $selectedData->order_class ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Prod Seq No') }}</label><p class="font-semibold">{{ $selectedData->prod_seq_no ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('HNS') }}</label><p class="font-semibold">{{ $selectedData->hns ?? '-' }}</p></div>
                </div>

                {{-- Info Relasi Schedule --}}
                @if($selectedData->schedule)
                    <x-hr />
                    <h4 class="font-semibold text-sm">{{ __('Schedule Information') }}</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="text-xs text-base-content/70">{{ __('Schedule Date') }}</label><p class="font-semibold">{{ $selectedData->schedule->schedule_date?->format('d/m/Y') ?? '-' }}</p></div>
                        <div><label class="text-xs text-base-content/70">{{ __('Schedule Time') }}</label><p class="font-semibold">{{ $selectedData->schedule->schedule_time?->format('H:i') ?? '-' }}</p></div>
                        <div><label class="text-xs text-base-content/70">{{ __('Adjusted Date') }}</label><p class="font-semibold">{{ $selectedData->schedule->adjusted_date?->format('d/m/Y') ?? '-' }}</p></div>
                        <div><label class="text-xs text-base-content/70">{{ __('Adjusted Time') }}</label><p class="font-semibold">{{ $selectedData->schedule->adjusted_time?->format('H:i') ?? '-' }}</p></div>
                        <div><label class="text-xs text-base-content/70">{{ __('Delivery Quantity') }}</label><p class="font-semibold">{{ $selectedData->schedule->delivery_quantity ?? '-' }}</p></div>
                        <div><label class="text-xs text-base-content/70">{{ __('Adjustment Quantity') }}</label><p class="font-semibold">{{ $selectedData->schedule->adjustment_quantity ?? '-' }}</p></div>
                    </div>
                @endif

                <x-hr />

                {{-- Info Timestamps --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="text-xs text-base-content/70">{{ __('Created At') }}</label><p class="font-semibold">{{ $selectedData->created_at?->format('d/m/Y H:i:s') ?? '-' }}</p></div>
                    <div><label class="text-xs text-base-content/70">{{ __('Updated At') }}</label><p class="font-semibold">{{ $selectedData->updated_at?->format('d/m/Y H:i:s') ?? '-' }}</p></div>
                </div>
            </div>
        @else
            {{-- Tampilan saat data masih loading --}}
            <x-alert :title="__('Loading')" :description="__('Fetching record details...')" icon="o-arrow-path" class="alert-info" />
        @endif

        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="closeDetailsModal" class="btn-ghost" />
            @if ($selectedData)
                <x-button :label="__('Delete')" class="btn-error"
                          wire:click="delete('{{ $selectedData->id }}')"
                          wire:confirm.prompt="{{ __('Are you sure?') }}\n{{ __('Type DELETE to confirm.') }}|DELETE" />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- Komponen Notifier (Sudah diperbaiki) --}}
    <livewire:components.ui.print-notifier />

    {{-- Script untuk refresh CSRF token dan handle errors --}}
    @script
    <script>
        // Refresh CSRF token setiap 30 menit untuk mencegah token expiration
        setInterval(() => {
            fetch('/csrf-token-refresh', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.token) {
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.token);
                    console.log('CSRF token refreshed successfully');
                }
            })
            .catch(error => {
                console.warn('Failed to refresh CSRF token:', error);
            });
        }, 30 * 60 * 1000); // 30 minutes

        // Handle Livewire CSRF errors
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status, response }) => {
                    if (status === 419) {
                        // CSRF token mismatch - reload page
                        alert('Session Anda telah berakhir. Halaman akan dimuat ulang.');
                        window.location.reload();
                    }
                });
            });
        });
    </script>
    @endscript

</div>
