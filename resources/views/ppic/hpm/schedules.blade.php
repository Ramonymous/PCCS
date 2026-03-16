<?php

use Livewire\Volt\Component;
use App\Models\Customer\HPM\Schedule;
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
use App\Imports\HpmScheduleImport;
use App\Exports\HpmScheduleTemplateExport;

new
#[Title('HPM Schedule Management')]
class extends Component {
    use WithPagination, WithFileUploads, Toast;

    /* ----------
    | Properti Filter & Penyortiran
    |-------------------------------------------------------------------------- */
    public string $searchSlipNumber    = '';
    public string $searchDateStart;
    public string $searchDateEnd;
    public array  $sortBy              = ['column' => 'effective_date', 'direction' => 'desc'];
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
    public array  $summary             = ['total' => 0, 'unique' => 0, 'updated' => 0];

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
     */
    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedIds);
    }

    /**
     * Headers tabel (cached - static data).
     */
    #[Computed]
    public function headers(): array
    {
        return Cache::remember('schedule_table_headers', 3600, function() {
            return [
                ['key' => 'slip_number',          'label' => 'Slip Number',       'sortable' => true],
                ['key' => 'schedule_date',        'label' => 'Schedule Date',     'sortable' => true],
                ['key' => 'adjusted_date',        'label' => 'Adjusted Date',     'sortable' => true],
                ['key' => 'schedule_time',        'label' => 'Schedule Time',     'sortable' => true],
                ['key' => 'adjusted_time',        'label' => 'Adjusted Time',     'sortable' => true],
                ['key' => 'delivery_quantity',    'label' => 'Delivery Qty',      'sortable' => true],
                ['key' => 'adjustment_quantity',  'label' => 'Adjustment Qty',    'sortable' => true],
            ];
        });
    }

    /* ----------
    | Data & Query
    |-------------------------------------------------------------------------- */

    /**
     * Menyiapkan data untuk tabel.
     */
    public function with(): array
    {
        return [
            'datas' => $this->dataQuery()->paginate($this->perPage),
            'headers' => $this->headers,
            'selectedCount' => $this->selectedCount,
        ];
    }

    /**
     * Membangun query utama untuk mengambil data Schedule.
     */
    public function dataQuery()
    {
        $query = Schedule::query()
            ->withCount('pccs') // Count related PCCs
            ->select('hpm_schedules.*',
                DB::raw('COALESCE(adjusted_date, schedule_date) as effective_date_alias')
            )
            ->when($this->searchSlipNumber, fn($q) => $q->where('slip_number', 'like', "%{$this->searchSlipNumber}%"))
            ->when($this->searchDateStart && $this->searchDateEnd,
                fn($q) => $q->where(function($q) {
                    $q->whereBetween('schedule_date', [$this->searchDateStart, $this->searchDateEnd])
                      ->orWhereBetween('adjusted_date', [$this->searchDateStart, $this->searchDateEnd]);
                })
            );

        $sortColumn = $this->sortBy['column'];
        $sortDirection = $this->sortBy['direction'];

        return match ($sortColumn) {
            'effective_date' => $query->orderBy('effective_date_alias', $sortDirection),
            default => $query->orderBy($sortColumn, $sortDirection),
        };
    }

    /* ----------
    | Fungsionalitas View Detail
    |-------------------------------------------------------------------------- */

    /**
     * Mengambil detail Schedule untuk modal.
     */
    public function viewDetails(string $id): void
    {
        // Cache detail view for 10 minutes (600 seconds)
        $cacheKey = "schedule_detail_{$id}";
        
        $this->selectedData = Cache::remember($cacheKey, 600, function() use ($id) {
            return Schedule::withCount('pccs')
                ->with(['pccs' => function($query) {
                    // Select both slip_barcode and kd_lot_no; display will prefer slip_barcode and fallback to kd_lot_no
                    $query->select('id', 'slip_no', 'slip_barcode', 'kd_lot_no', 'part_no', 'ship')
                          ->limit(10); // Limit related PCCs to show
                }])
                ->find($id);
        });

        if ($this->selectedData) {
            $this->detailsModal = true;
        } else {
            $this->warning("Data not found", null, 'toast-top toast-end');
        }
    }

    /* ----------
    | Fungsionalitas Delete
    |-------------------------------------------------------------------------- */

    /**
     * Menghapus data Schedule.
     */
    public function delete(string $id): void
    {
        try {
            $schedule = Schedule::find($id);
            
            if (!$schedule) {
                $this->error('Schedule not found', null, 'toast-top toast-end');
                return;
            }

            // Check if schedule has related PCCs
            $pccsCount = $schedule->pccs()->count();
            if ($pccsCount > 0) {
                $this->warning(
                    "Cannot delete schedule. It has {$pccsCount} related PCC record(s).",
                    null,
                    'toast-top toast-end'
                );
                return;
            }

            $slipNumber = $schedule->slip_number;
            $schedule->delete();

            // Clear caches
            Cache::forget("schedule_detail_{$id}");
            $this->clearQueryCache();

            Log::info("✅ Schedule deleted", ['slip_number' => $slipNumber, 'id' => $id]);
            $this->success("Schedule '{$slipNumber}' deleted successfully", null, 'toast-top toast-end');

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error("❌ Failed to delete schedule", [
                'user_id' => auth()->id(),
                'schedule_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred while deleting data. Please try again.'), null, 'toast-top toast-end');
        }
    }

    /**
     * Bulk delete schedules.
     */
    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning('No schedules selected', null, 'toast-top toast-end');
            return;
        }

        try {
            // Check for schedules with related PCCs
            $schedulesWithPccs = Schedule::whereIn('id', $this->selectedIds)
                ->has('pccs')
                ->count();

            if ($schedulesWithPccs > 0) {
                $this->warning(
                    "{$schedulesWithPccs} schedule(s) cannot be deleted because they have related PCC records.",
                    null,
                    'toast-top toast-end'
                );
                return;
            }

            $count = Schedule::whereIn('id', $this->selectedIds)->delete();
            
            // Clear caches
            foreach ($this->selectedIds as $id) {
                Cache::forget("schedule_detail_{$id}");
            }
            $this->clearQueryCache();

            $this->selectedIds = [];
            
            Log::info("✅ Bulk delete schedules", ['count' => $count]);
            $this->success("{$count} schedule(s) deleted successfully", null, 'toast-top toast-end');

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error("❌ Failed bulk delete schedules", [
                'user_id' => auth()->id(),
                'selected_count' => count($this->selectedIds),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred while deleting data. Please try again.'), null, 'toast-top toast-end');
        }
    }

    /* ----------
    | Filter Management
    |-------------------------------------------------------------------------- */

    /**
     * Membersihkan semua filter pencarian.
     */
    public function clearFilters(): void
    {
        $this->reset(['searchSlipNumber']);
        $this->searchDateStart = now()->startOfMonth()->format('Y-m-d');
        $this->searchDateEnd   = now()->endOfMonth()->format('Y-m-d');
        $this->clearQueryCache();
        $this->resetPage();
    }

    /**
     * Helper untuk clear query cache.
     */
    private function clearQueryCache(): void
    {
        Cache::flush();
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
            'searchSlipNumber', 'searchDateStart', 'searchDateEnd', 'perPage', 'sortBy'
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
        $this->showImportModal = true;
        $this->reset(['importFile', 'summary']);
    }

    /**
     * Menutup modal import.
     */
    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'summary']);
    }

    /**
     * Download template Excel.
     */
    public function downloadTemplate()
    {
        try {
            Log::info("📥 Downloading schedule template");
            return Excel::download(new HpmScheduleTemplateExport, 'hpm_schedule_template.xlsx');
        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error("❌ Failed to download schedule template", [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred while downloading template. Please try again.'), null, 'toast-top toast-end');
        }
    }

    /**
     * Import data dari Excel.
     */
    public function importSchedules(): void
    {
        $this->validate();

        if (!$this->importFile) {
            $this->warning('Please upload a file', null, 'toast-top toast-end');
            return;
        }

        try {
            Log::info("📤 Starting schedule import", [
                'filename' => $this->importFile->getClientOriginalName(),
                'size' => $this->importFile->getSize()
            ]);

            // Persist upload to a stable local disk path to avoid temp-file issues
            $disk = 'local';
            Storage::disk($disk)->makeDirectory('imports_temp');
            $tmpPath = $this->importFile->store('imports_temp', $disk);

            $import = new HpmScheduleImport();
            Excel::import($import, $tmpPath, $disk);

            $this->summary = $import->getSummary();

            // Cleanup temporary file on the same disk
            Storage::disk($disk)->delete($tmpPath);

            // Adjust filters to make imported data visible (use overall min/max dates)
            $minDate = Schedule::min('schedule_date');
            $maxDate = Schedule::max('adjusted_date') ?? Schedule::max('schedule_date');
            if ($minDate) {
                $this->searchDateStart = \Carbon\Carbon::parse($minDate)->subDay()->format('Y-m-d');
            }
            if ($maxDate) {
                $this->searchDateEnd = \Carbon\Carbon::parse($maxDate)->addDay()->format('Y-m-d');
            }

            $totalInDb = Schedule::count();

            Log::info("✅ Schedule import completed", array_merge($this->summary, ['total_in_db' => $totalInDb]));

            $this->success(
                sprintf('Import completed! Total: %d, Unique: %d, Updated: %d. Records in DB: %d',
                    $this->summary['total'],
                    $this->summary['unique'],
                    $this->summary['updated'],
                    $totalInDb
                ),
                null,
                'toast-top toast-end'
            );

            // Clear cache after successful import
            $this->clearQueryCache();
            
            $this->closeImportModal();
            $this->resetPage();

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error("❌ Schedule import failed", [
                'user_id' => auth()->id(),
                'filename' => $this->importFile?->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Show generic error to user
            $this->error(__('An error occurred during import process. Please check file format and try again.'), null, 'toast-top toast-end');
        }
    }
}; ?>

<div>
    {{-- HEADER --}}
    <x-header :title="__('HPM Schedule Management')" :subtitle="__('Manage Production Schedules')" separator progress-indicator>
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
    <x-card :title="__('Search Filters')" :subtitle="__('Filter schedule records')" class="mb-4" shadow separator>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-input 
                :label="__('Slip Number')" 
                wire:model.live.debounce.300ms="searchSlipNumber" 
                placeholder="Filter by slip number" 
                icon="o-magnifying-glass" 
                clearable />
            <x-input 
                :label="__('Date From')" 
                wire:model.live.debounce.300ms="searchDateStart" 
                type="date" 
                icon="o-calendar" />
            <x-input 
                :label="__('Date To')" 
                wire:model.live.debounce.300ms="searchDateEnd" 
                type="date" 
                icon="o-calendar" />
        </div>
        <x-slot:actions>
            <x-button 
                :label="__('Clear Filters')" 
                wire:click="clearFilters" 
                icon="o-x-mark" 
                spinner="clearFilters" 
                class="btn-ghost" />
        </x-slot:actions>
    </x-card>

    {{-- TABLE --}}
    <x-card :title="__('Schedule Records')" shadow separator>
        <x-slot:menu class="flex items-center gap-2">
            @if($selectedCount > 0)
                <x-button
                    :label="__('Delete Selected')"
                    icon="o-trash"
                    wire:click="bulkDelete"
                    wire:confirm.prompt="Are you sure?\nType DELETE to confirm.|DELETE"
                    class="btn-error"
                    spinner="bulkDelete"
                    :badge="$selectedCount"
                    badge-classes="badge-warning"
                    responsive />
            @endif
        </x-slot:menu>

        <x-table 
            :headers="$headers" 
            :rows="$datas" 
            wire:model.live="selectedIds"
            selectable 
            with-pagination 
            selectable-key="id" 
            :per-page-values="[50, 100, 150, 200, 250]" 
            :sort-by="$sortBy">

            {{-- Slip Number with related PCCs count badge --}}
            @scope('cell_slip_number', $data)
                <div class="flex items-center gap-2">
                    <span class="font-mono">{{ $data->slip_number }}</span>
                    @if(($data->pccs_count ?? 0) > 0)
                        <x-badge :value="$data->pccs_count" class="badge-info" tooltip="Related PCCs count" />
                    @endif
                </div>
            @endscope

            {{-- Format Schedule Date --}}
            @scope('cell_schedule_date', $data)
                {{ $data->schedule_date ? $data->schedule_date->format('d/m/Y') : '-' }}
            @endscope

            {{-- Format Adjusted Date --}}
            @scope('cell_adjusted_date', $data)
                {{ $data->adjusted_date ? $data->adjusted_date->format('d/m/Y') : '-' }}
            @endscope

            {{-- Format Schedule Time --}}
            @scope('cell_schedule_time', $data)
                {{ $data->schedule_time ? substr($data->schedule_time, 0, 5) : '-' }}
            @endscope

            {{-- Format Adjusted Time --}}
            @scope('cell_adjusted_time', $data)
                {{ $data->adjusted_time ? substr($data->adjusted_time, 0, 5) : '-' }}
            @endscope

            {{-- Actions Column --}}
            @scope('actions', $data)
                <div class="flex gap-1">
                    <x-button 
                        icon="o-eye" 
                        class="btn-ghost btn-sm text-info" 
                        wire:click="viewDetails('{{ $data->id }}')" 
                        tooltip-left="View Details" />
                    <x-button 
                        icon="o-trash" 
                        class="btn-ghost btn-sm text-error"
                        wire:click="delete('{{ $data->id }}')"
                        wire:confirm.prompt="Are you sure?\nType DELETE to confirm.|DELETE"
                        tooltip-left="Delete" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- IMPORT MODAL --}}
    <x-modal wire:model="showImportModal" :title="__('Import Schedule Data')" class="backdrop-blur" persistent separator>
        <div class="space-y-6">
            <x-alert :title="__('Information')" icon="o-information-circle" class="alert-info">
                <p>Ensure your Excel file uses the correct template. The 'slip_number' column is required. If a slip number already exists, its schedule will be updated.</p>
            </x-alert>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Download Template --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-primary badge-lg">1</span>
                        <h3 class="font-semibold text-lg">{{ __('Download Template') }}</h3>
                    </div>
                    <p class="text-sm text-base-content/70">Download the official Excel template to ensure correct data format.</p>
                    <x-button 
                        :label="__('Download Template')" 
                        icon="o-arrow-down-tray" 
                        wire:click="downloadTemplate" 
                        spinner="downloadTemplate" 
                        class="btn-info" />
                </div>
                
                {{-- Upload File --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-primary badge-lg">2</span>
                        <h3 class="font-semibold text-lg">{{ __('Upload Your Data') }}</h3>
                    </div>
                    <p class="text-sm text-base-content/70">Upload your completed Excel file (.xlsx, .xls) here.</p>
                    <x-file 
                        wire:model="importFile" 
                        accept=".xlsx,.xls" 
                        hint="Maximum 5MB, .xlsx or .xls format" />
                    @error('importFile')
                        <x-alert 
                            :title="__('Validation Error')" 
                            description="{{ $message }}" 
                            icon="o-exclamation-triangle" 
                            class="alert-error mt-3" />
                    @enderror
                </div>
            </div>
        </div>
        
        <x-slot:actions>
            <x-button 
                :label="__('Cancel')" 
                wire:click="closeImportModal" 
                class="btn-ghost" />
            <x-button 
                :label="__('Import Data')" 
                class="btn-primary" 
                wire:click="importSchedules" 
                spinner="importSchedules" 
                :disabled="!$importFile" />
        </x-slot:actions>
    </x-modal>

    {{-- DETAIL MODAL --}}
    <x-modal wire:model="detailsModal" :title="__('Schedule Details')" class="backdrop-blur" separator>
        @if ($selectedData)
            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                {{-- Main Information --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Slip Number') }}</label>
                        <p class="font-semibold">{{ $selectedData->slip_number }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Related PCCs') }}</label>
                        <p class="font-semibold">{{ $selectedData->pccs_count }} record(s)</p>
                    </div>
                </div>

                <x-hr />

                {{-- Schedule Information --}}
                <h4 class="font-semibold text-sm">{{ __('Schedule Information') }}</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Schedule Date') }}</label>
                        <p class="font-semibold">{{ $selectedData->schedule_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Schedule Time') }}</label>
                        <p class="font-semibold">{{ $selectedData->schedule_time ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Adjusted Date') }}</label>
                        <p class="font-semibold">{{ $selectedData->adjusted_date?->format('d/m/Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Adjusted Time') }}</label>
                        <p class="font-semibold">{{ $selectedData->adjusted_time ?? '-' }}</p>
                    </div>
                </div>

                <x-hr />

                {{-- Quantity Information --}}
                <h4 class="font-semibold text-sm">{{ __('Quantity Information') }}</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Delivery Quantity') }}</label>
                        <p class="font-semibold">{{ number_format($selectedData->delivery_quantity) }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Adjustment Quantity') }}</label>
                        <p class="font-semibold">{{ number_format($selectedData->adjustment_quantity) }}</p>
                    </div>
                </div>

                {{-- Related PCCs (if any) --}}
                @if($selectedData->pccs && $selectedData->pccs->count() > 0)
                    <x-hr />
                    <h4 class="font-semibold text-sm">{{ __('Related PCCs') }} ({{ __('Showing first 10') }})</h4>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Slip Barcode') }}</th>
                                    <th>{{ __('Part No') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedData->pccs as $pcc)
                                    <tr>
                                        <td>{{ $pcc->slip_barcode ?: $pcc->kd_lot_no ?: '-' }}</td>
                                        <td>{{ $pcc->part_no }}</td>
                                        <td>{{ $pcc->ship }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Timestamps --}}
                <x-hr />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-base-content/50">
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Created At') }}</label>
                        <p>{{ $selectedData->created_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">{{ __('Updated At') }}</label>
                        <p>{{ $selectedData->updated_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('detailsModal', false)" class="btn-ghost" />
        </x-slot:actions>
    </x-modal>
</div>
