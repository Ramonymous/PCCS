<?php

use function Livewire\Volt\{state, rules, on};
use App\Models\Master\FinishGood;
use App\Models\Master\Customer;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\FinishGoodImport;
use App\Exports\FinishGoodTemplateExport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new class extends \Livewire\Volt\Component {
    use Toast, WithPagination, WithFileUploads;

    public int  $perPage = 10;
    public bool $showModal = false;
    public bool $showImportModal = false;

    // Search & Sort
    public string $search = '';
    public array $sortBy = ['column' => 'part_number', 'direction' => 'asc'];
    public ?string $filterCustomer = null;

    // form
    public ?string $fgId = null;
    public ?string $customer_id = null;
    public string $part_number = '';
    public string $part_name = '';
    public string $alias = '';
    public string $model = '';
    public string $variant = '';
    public string $wh_address = '';
    public string $type = 'ASSY';
    public int    $stock = 0;
    public bool   $is_active = true;

    // Import
    #[Validate('nullable|file|mimes:xlsx,xls|max:5120')]
    public $importFile = null;
    public array $summary = ['total' => 0, 'duplicates' => 0, 'unique' => 0];

    // Reset pagination when filters change
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCustomer(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function customers()
    {
        return Customer::select('id', 'name', 'code')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function customersMap(): array
    {
        return Customer::pluck('name', 'id')->toArray();
    }

    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1 hidden'],
            ['key' => 'customer', 'label' => 'Customer', 'sortable' => false],
            ['key' => 'part_number', 'label' => 'Part Number', 'sortable' => true],
            ['key' => 'part_name', 'label' => 'Part Name', 'sortable' => true],
            ['key' => 'alias', 'label' => 'Alias'],
            ['key' => 'model', 'label' => 'Model', 'sortable' => true],
            ['key' => 'variant', 'label' => 'Variant'],
            ['key' => 'type', 'label' => 'Type', 'sortable' => true],
            ['key' => 'wh_address', 'label' => 'WH Address'],
            ['key' => 'stock', 'label' => 'Stock', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Active'],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-20'],
        ];
    }

    #[Computed]
    public function finishGoods()
    {
        return FinishGood::query()
            ->with('customer:id,name,code')
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('part_number', 'like', "%{$this->search}%")
                        ->orWhere('part_name', 'like', "%{$this->search}%")
                        ->orWhere('alias', 'like', "%{$this->search}%")
                        ->orWhere('model', 'like', "%{$this->search}%")
                        ->orWhere('variant', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterCustomer, fn($q) => $q->where('customer_id', $this->filterCustomer))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate($this->perPage);
    }

    // Expose data to view
    public function with(): array
    {
        return [
            'finishGoodsData' => $this->finishGoods,
            'headersData' => $this->headers,
            'customersData' => $this->customers,
            'customersMapData' => $this->customersMap,
        ];
    }

    public function openCreate(): void
    {
        $this->reset('fgId','customer_id','part_number','part_name','alias','model','variant','wh_address','type','stock','is_active');
        $this->stock = 0;
        $this->type = 'ASSY';
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(FinishGood $finishGood): void
    {
        $this->fgId = $finishGood->id;
        $this->customer_id = $finishGood->customer_id;
        $this->part_number = $finishGood->part_number ?? '';
        $this->part_name = $finishGood->part_name ?? '';
        $this->alias = $finishGood->alias ?? '';
        $this->model = $finishGood->model ?? '';
        $this->variant = $finishGood->variant ?? '';
        $this->wh_address = $finishGood->wh_address ?? '';
        $this->type = $finishGood->type ?? 'ASSY';
        $this->stock = $finishGood->stock ?? 0;
        $this->is_active = (bool) ($finishGood->is_active ?? false);
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'customer_id' => 'required|string|exists:customers,id',
            'part_number' => 'required|string|max:255',
            'part_name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'variant' => 'nullable|string|max:255',
            'wh_address' => 'nullable|string|max:255',
            'type' => 'required|in:ASSY,DIRECT',
            'stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];

        $this->validate($rules);

        $data = [
            'customer_id' => $this->customer_id,
            'part_number' => $this->part_number,
            'part_name' => $this->part_name,
            'alias' => $this->alias ?: null,
            'model' => $this->model ?: null,
            'variant' => $this->variant ?: null,
            'wh_address' => $this->wh_address ?: null,
            'type' => $this->type,
            'is_active' => $this->is_active ? 1 : 0,
        ];

        if ($this->fgId) {
            FinishGood::updateOrCreate(['id' => $this->fgId], $data);
            $this->success('Finish Good updated');
        } else {
            $data['stock'] = $this->stock ?? 0;
            FinishGood::create($data);
            $this->success('Finish Good created');
        }
        $this->showModal = false;
        unset($this->finishGoods, $this->customers); // Clear computed cache
    }

    public function delete(FinishGood $finishGood): void
    {
        $finishGood->delete();
        $this->success('Finish Good deleted');
        unset($this->finishGoods); // Clear computed cache
    }

    // Import/Export Functions
    public function openImportModal(): void
    {
        $this->reset(['importFile', 'summary']);
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->reset(['importFile', 'summary']);
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new FinishGoodTemplateExport, 'finish_goods_template.xlsx');
        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error('Finish Good Template Download Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error('Terjadi kesalahan saat mengunduh template. Silakan coba lagi.', null, 'toast-top toast-end');
        }
    }

    public function importFinishGoods(): void
    {
        $this->validateOnly('importFile');
        
        if (!$this->importFile) {
            $this->warning('File import tidak ditemukan.', null, 'toast-top toast-end');
            return;
        }

        try {
            // Log import attempt
            Log::info('Finish Good Import Started', [
                'user_id' => auth()->id(),
                'filename' => $this->importFile->getClientOriginalName(),
                'size' => $this->importFile->getSize(),
            ]);
            
            // Persist upload to local disk to ensure cross-OS consistency
            $disk = 'local';
            Storage::disk($disk)->makeDirectory('imports_temp');
            $tmpPath = $this->importFile->store('imports_temp', $disk);

            $import = new FinishGoodImport;

            // Import using disk + relative path for reliability (Linux/Windows/queues)
            Excel::import($import, $tmpPath, $disk);

            // Ambil ringkasan dari kelas import
            $this->summary = [
                'total' => method_exists($import, 'getRowCount') ? $import->getRowCount() : 0,
                'duplicates' => method_exists($import, 'getDuplicateCount') ? $import->getDuplicateCount() : 0,
                'unique' => method_exists($import, 'getUniqueCount') ? $import->getUniqueCount() : 0,
            ];

            Log::info('Finish Good Import Completed', $this->summary);

            // Cleanup temporary file
            Storage::disk($disk)->delete($tmpPath);

            if ($this->summary['unique'] > 0) {
                $this->success('Import Selesai! ' . $this->summary['unique'] . ' data unik berhasil disimpan.');
            } else {
                $this->warning('File kosong atau tidak ada data baru yang valid untuk diimpor.', null, 'toast-top toast-end');
            }

            $this->closeImportModal();
            unset($this->finishGoods); // Clear computed cache

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Tangani error validasi dari Maatwebsite/Excel
            $failures = $e->failures();
            $first = $failures[0] ?? null;
            
            // Log detailed validation errors
            Log::warning('Finish Good Import Validation Failed', [
                'user_id' => auth()->id(),
                'failures_count' => count($failures),
                'first_error' => $first ? [
                    'row' => $first->row(),
                    'attribute' => $first->attribute(),
                    'errors' => $first->errors(),
                ] : null,
            ]);
            
            // Show specific validation error to user
            $msg = $first ? "Error pada baris {$first->row()}: {$first->errors()[0]}" : 'Format file tidak sesuai';
            $this->error('Gagal Validasi: ' . $msg, null, 'toast-top toast-end');

        } catch (\Exception $e) {
            // Log detailed error for debugging
            Log::error('Finish Good Import Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Show generic error to user
            $this->error('Terjadi kesalahan saat proses import. Silakan periksa format file dan coba lagi.', null, 'toast-top toast-end');
        }
    }

    private function success(string $title): void
    {
        $this->toast('success', $title, '', 'toast-top toast-end', 'o-check-circle', 'alert-success', 3000);
    }

    private function warning(string $title): void
    {
        $this->toast('warning', $title, '', 'toast-top toast-end', 'o-exclamation-triangle', 'alert-warning', 5000);
    }

    private function error(string $title): void
    {
        $this->toast('error', $title, '', 'toast-top toast-end', 'o-x-circle', 'alert-error', 5000);
    }
};
?>
<div>
    <x-header title="Finish Goods" subtitle="Manage finished goods" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search parts..." wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select placeholder="All Customers" :options="$customersData" option-label="name" option-value="id" 
                      wire:model.live="filterCustomer" icon="o-funnel" class="w-48" />
            <x-button label="Import" icon="o-arrow-up-tray" class="btn-outline" wire:click="openImportModal" spinner="openImportModal" />
            <x-button label="Create" icon="o-plus" class="btn-primary" wire:click="openCreate" spinner="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headersData" :rows="$finishGoodsData" :sort-by="$sortBy" with-pagination 
                 per-page="perPage" :per-page-values="[5,10,20,50]">
            @scope('cell_customer', $fg)
                <div class="text-sm font-medium">{{ $fg->customer->name ?? '-' }}</div>
                @if($fg->customer?->code)
                    <div class="text-xs text-gray-500 font-mono">{{ $fg->customer->code }}</div>
                @endif
            @endscope

            @scope('cell_alias', $fg)
                <span class="text-sm">{{ $fg->alias ?: '-' }}</span>
            @endscope

            @scope('cell_variant', $fg)
                <span class="text-sm">{{ $fg->variant ?: '-' }}</span>
            @endscope

            @scope('cell_type', $fg)
                @if($fg->type === 'DIRECT')
                    <span class="badge badge-info badge-sm">DIRECT</span>
                @else
                    <span class="badge badge-primary badge-sm">ASSY</span>
                @endif
            @endscope

            @scope('cell_wh_address', $fg)
                <span class="text-sm font-mono">{{ $fg->wh_address ?: '-' }}</span>
            @endscope

            @scope('cell_stock', $fg)
                <span class="badge badge-neutral badge-sm font-mono">{{ number_format($fg->stock) }}</span>
            @endscope

            @scope('cell_is_active', $fg)
                @if($fg->is_active)
                    <span class="badge badge-success badge-sm">Active</span>
                @else
                    <span class="badge badge-ghost badge-sm">Inactive</span>
                @endif
            @endscope

            @scope('actions', $fg)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="openEdit('{{ $fg->id }}')" tooltip="Edit" spinner="openEdit" />
                    <x-button icon="o-trash" wire:click="delete('{{ $fg->id }}')" tooltip="Delete" 
                              spinner="delete" onclick="confirm('Delete this finish good?') || event.stopImmediatePropagation()" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showModal" title="{{ $fgId ? 'Edit Finish Good' : 'Create Finish Good' }}" separator class="backdrop-blur">
        <x-select label="Customer" :options="$customersData" option-label="name" option-value="id" 
                  wire:model="customer_id" placeholder="Select customer" icon="o-building-office" />
        
        <div class="grid grid-cols-2 gap-3">
            <x-input label="Part Number" wire:model.defer="part_number" icon="o-hashtag" />
            <x-input label="Model" wire:model.defer="model" icon="o-cube" />
        </div>
        
        <x-input label="Part Name" wire:model.defer="part_name" icon="o-tag" />
        
        <div class="grid grid-cols-2 gap-3">
            <x-input label="Alias (Optional)" wire:model.defer="alias" icon="o-identification" />
            <x-input label="Variant (Optional)" wire:model.defer="variant" icon="o-squares-2x2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-input label="Warehouse Address" wire:model.defer="wh_address" icon="o-map-pin" placeholder="e.g., A1-B2" />
            <x-select label="Type" :options="[['id' => 'ASSY', 'name' => 'ASSY'], ['id' => 'DIRECT', 'name' => 'DIRECT']]" 
                      option-label="name" option-value="id" wire:model="type" icon="o-cube-transparent" />
        </div>

        @if(!$fgId)
            <x-input label="Initial Stock" wire:model.defer="stock" type="number" min="0" 
                     hint="Stock can only be set during creation" icon="o-archive-box" />
        @else
            <x-alert title="Stock Management" icon="o-information-circle" class="alert-info">
                Current stock: <strong class="font-mono">{{ number_format($stock) }}</strong> units. 
                Stock adjustments should be made through inventory transactions.
            </x-alert>
        @endif
        
        <x-toggle label="Active Status" wire:model.defer="is_active" />

        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('showModal', false)" />
            <x-button label="Save" icon="o-check" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-modal>

    {{-- IMPORT MODAL --}}
    <x-modal wire:model="showImportModal" title="Import Finish Goods" class="backdrop-blur" persistent separator>
        <div class="space-y-6">
            <x-alert title="Peringatan" icon="o-exclamation-triangle" class="alert-warning">
                <p>Pastikan file Excel Anda menggunakan template yang benar. Kolom wajib: customer_code, part_number, part_name, type.</p>
            </x-alert>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Download Template --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <x-icon name="o-document-arrow-down" class="w-5 h-5 text-primary" />
                        <span class="font-semibold">Download Template</span>
                    </div>
                    <p class="text-sm text-base-content/70">Download template Excel untuk format yang benar.</p>
                    <x-button label="Download Template" icon="o-arrow-down-tray" 
                              wire:click="downloadTemplate" spinner="downloadTemplate" class="btn-outline btn-sm" />
                </div>
                
                {{-- Upload File --}}
                <div class="flex flex-col space-y-3 p-4 border rounded-lg">
                    <div class="flex items-center space-x-2">
                        <x-icon name="o-arrow-up-tray" class="w-5 h-5 text-success" />
                        <span class="font-semibold">Upload Data</span>
                    </div>
                    <p class="text-sm text-base-content/70">Upload file Excel yang sudah diisi (.xlsx, .xls).</p>
                    <x-file wire:model="importFile" accept=".xlsx,.xls" 
                            hint="Maksimal 5MB, format .xlsx atau .xls" />
                    @error('importFile')
                        <span class="text-error text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            @if($summary['total'] > 0)
                <x-alert title="Hasil Import" icon="o-check-circle" class="alert-success">
                    <ul class="text-sm space-y-1">
                        <li>Total baris: <strong>{{ $summary['total'] }}</strong></li>
                        <li>Duplikat: <strong>{{ $summary['duplicates'] }}</strong></li>
                        <li>Berhasil disimpan: <strong>{{ $summary['unique'] }}</strong></li>
                    </ul>
                </x-alert>
            @endif
        </div>
        
        <x-slot:actions>
            <x-button label="Batal" wire:click="closeImportModal" class="btn-ghost" />
            <x-button label="Import Data" class="btn-primary" wire:click="importFinishGoods" 
                      spinner="importFinishGoods" :disabled="!$importFile" />
        </x-slot:actions>
    </x-modal>
</div>
