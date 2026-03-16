<?php

use App\Models\Customer\HPM\CCP;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;

new
#[Title('Critical Check Points Management')]
class extends Component {
    use WithPagination, WithFileUploads, Toast;

    /* ----------
    | Properti Filter & Penyortiran
    |-------------------------------------------------------------------------- */
    public string $searchFinishGood    = '';
    public string $searchRevision      = '';
    public string $filterActive        = 'all'; // all, active, inactive
    public array  $sortBy              = ['column' => 'finish_good_id', 'direction' => 'asc'];
    public int    $perPage             = 50;

    /* ----------
    | Properti Seleksi Data
    |-------------------------------------------------------------------------- */
    public array  $selectedIds         = [];

    /* ----------
    | Properti Modal Detail
    |-------------------------------------------------------------------------- */
    public bool   $detailsModal        = false;
    public        $selectedData        = null;

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
     */
    #[Computed]
    public function headers(): array
    {
        return Cache::remember('ccp_table_headers', 3600, function() {
            return [
                ['key' => 'finish_good_id', 'label' => 'Finish Good', 'sortable' => true],
                ['key' => 'stage', 'label' => 'Stage', 'sortable' => true],
                ['key' => 'check_point_img', 'label' => 'Image', 'sortable' => false],
                ['key' => 'revision', 'label' => 'Revision', 'sortable' => true],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
                ['key' => 'actions', 'label' => 'Actions', 'sortable' => false],
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
            'datas'   => $this->dataQuery()->paginate($this->perPage),
            'headers' => $this->headers,
            'selectedCount' => $this->selectedCount,
        ];
    }

    /**
     * Membangun query utama untuk mengambil data CCP.
     */
    public function dataQuery()
    {
        $query = CCP::query()
            ->with('finishGood:id,part_number,part_name,model,variant')
            ->when($this->searchFinishGood, fn($q) => $q->where('finish_good_id', 'like', "%{$this->searchFinishGood}%"))
            ->when($this->searchRevision, fn($q) => $q->where('revision', 'like', "%{$this->searchRevision}%"))
            ->when($this->filterActive === 'active', fn($q) => $q->where('is_active', true))
            ->when($this->filterActive === 'inactive', fn($q) => $q->where('is_active', false));

        $sortColumn = $this->sortBy['column'];
        $sortDirection = $this->sortBy['direction'];

        return $query->orderBy($sortColumn, $sortDirection);
    }

    /* ----------
    | Aksi CRUD & UI
    |-------------------------------------------------------------------------- */

    /**
     * Menampilkan detail item dalam modal dengan caching.
     */
    public function viewDetails(string $id): void
    {
        // Cache detail data untuk 10 menit
        $this->selectedData = Cache::remember("ccp_detail_{$id}", 600, function() use ($id) {
            return CCP::with('finishGood')->find($id);
        });

        if (!$this->selectedData) {
            $this->error(__('Data not found.'), null, 'toast-top toast-end');
            return;
        }
        $this->detailsModal = true;
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
     * Menghapus data CCP dan invalidate cache.
     */
    public function delete(string $id): void
    {
        $data = CCP::find($id);
        if (!$data) {
            $this->error(__('Data not found.'), null, 'toast-top toast-end');
            return;
        }
        
        // Delete image file if exists
        if ($data->check_point_img && Storage::exists('hpm/ccp/' . $data->check_point_img)) {
            Storage::delete('hpm/ccp/' . $data->check_point_img);
        }
        
        $data->delete();
        
        // Invalidate related caches
        Cache::forget("ccp_detail_{$id}");
        $this->clearQueryCache();
        
        $this->success(__('Data deleted successfully.'), null, 'toast-top toast-end');
        $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));

        if ($this->selectedData && $this->selectedData->id == $id) {
            $this->closeDetailsModal();
        }
        $this->resetPage();
    }

    /**
     * Bulk delete selected items.
     */
    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            $this->warning(__('Select at least one item to delete.'), null, 'toast-top toast-end');
            return;
        }

        $validIds = CCP::whereIn('id', $this->selectedIds)->pluck('id')->toArray();
        
        if (empty($validIds)) {
            $this->error(__('No valid data selected.'), null, 'toast-top toast-end');
            return;
        }

        // Delete images for all selected items
        $items = CCP::whereIn('id', $validIds)->get();
        foreach ($items as $item) {
            if ($item->check_point_img && Storage::exists('hpm/ccp/' . $item->check_point_img)) {
                Storage::delete('hpm/ccp/' . $item->check_point_img);
            }
            Cache::forget("ccp_detail_{$item->id}");
        }

        CCP::whereIn('id', $validIds)->delete();
        
        $this->clearQueryCache();
        $this->success(__(':count data deleted successfully.', ['count' => count($validIds)]), null, 'toast-top toast-end');
        $this->selectedIds = [];
        $this->resetPage();
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(string $id): void
    {
        $data = CCP::find($id);
        if (!$data) {
            $this->error(__('Data not found.'), null, 'toast-top toast-end');
            return;
        }

        $data->is_active = !$data->is_active;
        $data->save();

        Cache::forget("ccp_detail_{$id}");
        $this->clearQueryCache();

        $statusLabel = $data->is_active ? __('ACTIVE') : __('INACTIVE');
        $this->success(__('Status updated to :status successfully.', ['status' => $statusLabel]), null, 'toast-top toast-end');
    }

    /**
     * Membersihkan semua filter pencarian dan cache.
     */
    public function clearFilters(): void
    {
        $this->reset(['searchFinishGood', 'searchRevision', 'filterActive']);
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
            'searchFinishGood', 'searchRevision',
            'filterActive', 'perPage', 'sortBy'
        ])) {
            $this->clearQueryCache();
            $this->resetPage();
        }
    }
}
?>

<div>
    {{-- HEADER --}}
    <x-header title="Critical Check Points" subtitle="Manage Finish Good Quality Checkpoints" separator progress-indicator>
        <x-slot:actions>
            <x-button
                label="Create CCP"
                icon="o-plus"
                link="{{ route('qa.hpm.ccp.create') }}"
                class="btn-primary"
                responsive />
        </x-slot:actions>
    </x-header>

    {{-- FILTERS --}}
    <x-card title="Search Filters" subtitle="Filter records" class="mb-4" shadow separator>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <x-input 
                label="Finish Good ID" 
                wire:model.live.debounce.300ms="searchFinishGood" 
                placeholder="Filter by finish good" 
                icon="o-magnifying-glass" 
                clearable />
            <x-input 
                label="Revision" 
                wire:model.live.debounce.300ms="searchRevision" 
                placeholder="Filter by revision" 
                icon="o-magnifying-glass" 
                clearable />
            <x-select 
                label="Status" 
                wire:model.live="filterActive" 
                :options="[
                    ['id' => 'all', 'name' => 'All Status'],
                    ['id' => 'active', 'name' => 'Active Only'],
                    ['id' => 'inactive', 'name' => 'Inactive Only']
                ]" 
                icon="o-funnel" />
        </div>
        <x-slot:actions>
            <x-button 
                label="Clear Filters" 
                wire:click="clearFilters" 
                icon="o-x-mark" 
                spinner="clearFilters" 
                class="btn-ghost" />
        </x-slot:actions>
    </x-card>

    {{-- TABLE --}}
    <x-card title="CCP Records" shadow separator>
        <x-slot:menu class="flex items-center gap-2">
            <div wire:loading wire:target="selectedIds" class="text-sm text-gray-500">
                {{ __('Updating...') }}
            </div>
            @if($selectedCount > 0)
                <x-button
                    label="Delete Selected"
                    icon="o-trash"
                    wire:click="bulkDelete"
                    wire:confirm.prompt="{{ __('Are you sure?') }}\n{{ __('Type DELETE to confirm.') }}|DELETE"
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
            per-page="perPage" 
            :per-page-values="[25, 50, 100, 150]"
            :sort-by="$sortBy">
            
            {{-- Format Tampilan Kolom Finish Good --}}
            @scope('cell_finish_good_id', $data)
                <div>
                    @if($data->finishGood)
                        <p class="font-semibold">{{ $data->finishGood->part_number }}</p>
                        @if($data->finishGood->part_name)
                            <p class="text-xs text-blue-500">{{ $data->finishGood->part_name }}</p>
                        @endif
                    @else
                        <p class="font-semibold text-gray-400">{{ $data->finish_good_id }}</p>
                        <p class="text-xs text-red-500">No data</p>
                    @endif
                </div>
            @endscope

            {{-- Format Tampilan Kolom Stage --}}
            @scope('cell_stage', $data)
                <x-badge 
                    :value="$data->stage" 
                    class="badge-sm 
                        {{ $data->stage === 'PRODUCTION CHECK' ? 'badge-info' : '' }}
                        {{ $data->stage === 'PDI CHECK' ? 'badge-warning' : '' }}
                        {{ $data->stage === 'DELIVERY' ? 'badge-success' : '' }}
                        {{ $data->stage === 'ALL' ? 'badge-neutral' : '' }}" />
            @endscope

            {{-- Format Tampilan Kolom Image --}}
            @scope('cell_check_point_img', $data)
                @if($data->check_point_img)
                    <img 
                        src="{{ Storage::url('hpm/ccp/' . $data->check_point_img) }}" 
                        alt="CCP Image" 
                        class="h-12 w-12 object-cover rounded cursor-pointer hover:scale-110 transition-transform" 
                        wire:click="viewDetails('{{ $data->id }}')" />
                @else
                    <img 
                        src="https://placehold.co/600x400?text=Critical\nCheckpoint" 
                        alt="No Image" 
                        class="h-12 w-12 object-cover rounded opacity-50" />
                @endif
            @endscope

            {{-- Format Tampilan Kolom Status --}}
            @scope('cell_is_active', $data)
                <x-badge 
                    :value="$data->is_active ? 'Active' : 'Inactive'" 
                    class="badge-{{ $data->is_active ? 'success' : 'error' }} cursor-pointer"
                    wire:click="toggleActive('{{ $data->id }}')" />
            @endscope

            {{-- Kolom Aksi (View, Edit, Delete) --}}
            @scope('actions', $data)
                <div class="flex gap-1">
                    <x-button 
                        icon="o-eye" 
                        class="btn-ghost btn-sm text-info" 
                        wire:click="viewDetails('{{ $data->id }}')" 
                        tooltip-left="View Details" />
                    <x-button 
                        icon="o-pencil" 
                        class="btn-ghost btn-sm text-warning" 
                        link="{{ route('qa.hpm.ccp.edit', $data->id) }}" 
                        tooltip-left="Edit" />
                    <x-button 
                        icon="o-trash" 
                        class="btn-ghost btn-sm text-error"
                        wire:click="delete('{{ $data->id }}')"
                        wire:confirm.prompt="{{ __('Are you sure?') }}\n{{ __('Type DELETE to confirm.') }}|DELETE"
                        tooltip-left="Delete" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- DETAIL MODAL --}}
    <x-modal wire:model="detailsModal" title="Detail CCP Record" class="backdrop-blur" separator>
        @if ($selectedData)
            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                {{-- Bagian Image --}}
                <div class="flex justify-center mb-4">
                    @if($selectedData->check_point_img)
                        <img 
                            src="{{ Storage::url('hpm/ccp/' . $selectedData->check_point_img) }}" 
                            alt="CCP Image" 
                            class="max-h-64 rounded-lg shadow-lg" />
                    @else
                        <img 
                            src="https://placehold.co/600x400?text=Critical\nCheckpoint" 
                            alt="No Image" 
                            class="max-h-64 rounded-lg shadow-lg opacity-50" />
                    @endif
                </div>
                <x-hr />

                {{-- Bagian Utama --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($selectedData->finishGood)
                        <div>
                            <label class="text-xs text-base-content/70">Part Number</label>
                            <p class="font-semibold">{{ $selectedData->finishGood->part_number }}</p>
                            @if($selectedData->finishGood->part_name)
                                <p class="text-xs text-gray-500 mt-1">{{ $selectedData->finishGood->part_name }}</p>
                            @endif
                            @if($selectedData->finishGood->model || $selectedData->finishGood->variant)
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $selectedData->finishGood->model }}
                                    @if($selectedData->finishGood->variant) / {{ $selectedData->finishGood->variant }} @endif
                                </p>
                            @endif
                        </div>
                    @endif
                    <div>
                        <label class="text-xs text-base-content/70">Stage</label>
                        <x-badge 
                            :value="$selectedData->stage" 
                            class="
                                {{ $selectedData->stage === 'PRODUCTION CHECK' ? 'badge-info' : '' }}
                                {{ $selectedData->stage === 'PDI CHECK' ? 'badge-warning' : '' }}
                                {{ $selectedData->stage === 'DELIVERY' ? 'badge-success' : '' }}
                                {{ $selectedData->stage === 'ALL' ? 'badge-neutral' : '' }}" />
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">Revision</label>
                        <p class="font-semibold">{{ $selectedData->revision }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">Status</label>
                        <x-badge 
                            :value="$selectedData->is_active ? 'Active' : 'Inactive'" 
                            class="badge-{{ $selectedData->is_active ? 'success' : 'error' }}" />
                    </div>
                </div>

                <x-hr />

                {{-- Info Timestamps --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-base-content/70">Created At</label>
                        <p class="font-semibold">{{ $selectedData->created_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-base-content/70">Updated At</label>
                        <p class="font-semibold">{{ $selectedData->updated_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Tampilan saat data masih loading --}}
            <x-alert 
                title="Loading" 
                description="Mengambil detail data..." 
                icon="o-arrow-path" 
                class="alert-info" />
        @endif

        <x-slot:actions>
                <x-button 
                    :label="__('Close')" 
                    wire:click="closeDetailsModal" 
                    class="btn-ghost" />
            @if ($selectedData)
                <x-button 
                    label="Edit" 
                    class="btn-warning"
                    link="{{ route('qa.hpm.ccp.edit', $selectedData->id) }}" />
                <x-button 
                    label="Delete" 
                    class="btn-error"
                    wire:click="delete('{{ $selectedData->id }}')"
                    wire:confirm.prompt="{{ __('Are you sure?') }}\n{{ __('Type DELETE to confirm.') }}|DELETE" />
            @endif
        </x-slot:actions>
    </x-modal>

</div>
