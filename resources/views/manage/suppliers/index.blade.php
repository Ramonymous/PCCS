<?php

use function Livewire\Volt\{state, rules, on};
use App\Models\Master\Supplier;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use Toast, WithPagination;

    public int  $perPage = 10;
    public bool $showModal = false;

    // Search & Sort
    public string $search = '';
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // form
    public ?string $supplierId = null;
    public string $code = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    // Reset pagination when search changes
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1 hidden'],
            ['key' => 'code', 'label' => 'Code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ['key' => 'email', 'label' => 'Email', 'sortable' => true],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'address', 'label' => 'Address'],
            ['key' => 'is_active', 'label' => 'Active'],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-20'],
        ];
    }

    #[Computed]
    public function suppliers()
    {
        return Supplier::query()
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('address', 'like', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate($this->perPage);
    }

    // Expose data to view
    public function with(): array
    {
        return [
            'suppliersData' => $this->suppliers,
            'headersData' => $this->headers,
        ];
    }

    public function openCreate(): void
    {
        $this->reset('supplierId','code','name','email','phone','address','is_active');
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Supplier $supplier): void
    {
        $this->supplierId = $supplier->id;
        $this->code = $supplier->code ?? '';
        $this->name = $supplier->name ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->address = $supplier->address ?? '';
        $this->is_active = (bool) ($supplier->is_active ?? false);
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'code'   => 'nullable|string|max:255|unique:suppliers,code,'.$this->supplierId,
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255|unique:suppliers,email,'.$this->supplierId,
            'phone'  => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];

        $this->validate($rules);

        $data = [
            'code' => $this->code ?: null,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'is_active' => $this->is_active ? 1 : 0,
        ];

        if ($this->supplierId) {
            Supplier::updateOrCreate(['id' => $this->supplierId], $data);
            $this->success('Supplier updated');
        } else {
            Supplier::create($data);
            $this->success('Supplier created');
        }

        $this->showModal = false;
        unset($this->suppliers); // Clear computed cache
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
        $this->success('Supplier deleted');
        unset($this->suppliers); // Clear computed cache
    }

    private function success(string $title): void
    {
        $this->toast('success', $title, '', 'toast-top toast-end', 'o-check-circle', 'alert-success', 3000);
    }
};
?>
<div>
    <x-header :title="__('Suppliers')" :subtitle="__('Manage suppliers')" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input :placeholder="__('Search suppliers...')" wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button :label="__('Create')" icon="o-plus" class="btn-primary" wire:click="openCreate" spinner="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headersData" :rows="$suppliersData" :sort-by="$sortBy" with-pagination 
                 per-page="perPage" :per-page-values="[5,10,20,50]">
            @scope('cell_code', $supplier)
                <span class="text-sm font-mono">{{ $supplier->code ?: '-' }}</span>
            @endscope

            @scope('cell_address', $supplier)
                <div class="text-sm text-gray-700">{{ Str::limit($supplier->address, 80) }}</div>
            @endscope

            @scope('cell_is_active', $supplier)
                @if($supplier->is_active)
                    <span class="badge badge-success badge-sm">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-ghost badge-sm">Inactive</span>
                @endif
            @endscope

            @scope('actions', $supplier)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="openEdit('{{ $supplier->id }}')" tooltip="Edit" spinner="openEdit" />
                    <x-button icon="o-trash" wire:click="delete('{{ $supplier->id }}')" tooltip="Delete" 
                              spinner="delete" onclick="confirm('Delete this supplier?') || event.stopImmediatePropagation()" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showModal" title="{{ $supplierId ? 'Edit Supplier' : 'Create Supplier' }}" separator class="backdrop-blur">
        <x-input label="Code (Optional)" wire:model.defer="code" placeholder="e.g. SUP-001" icon="o-hashtag" />
        <x-input label="Supplier Name" wire:model.defer="name" icon="o-building-storefront" />
        
        <div class="grid grid-cols-2 gap-3">
            <x-input label="Email" wire:model.defer="email" type="email" icon="o-envelope" />
            <x-input label="Phone" wire:model.defer="phone" icon="o-phone" />
        </div>
        
        <x-textarea label="Address" wire:model.defer="address" rows="3" icon="o-map-pin" />
        <x-toggle label="Active Status" wire:model.defer="is_active" />

        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('showModal', false)" />
            <x-button label="Save" icon="o-check" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>
