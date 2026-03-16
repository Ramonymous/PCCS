<?php

use function Livewire\Volt\{state, rules, on};
use App\Models\Master\Customer;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use Toast, WithPagination;

    public int  $perPage = 10;
    public bool $showCustomerModal = false;

    // Search & Sort
    public string $search = '';
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // form
    public ?string $customerId = null;
    public string  $code       = '';
    public string  $name       = '';
    public string  $email      = '';
    public string  $phone      = '';
    public string  $address    = '';
    public bool    $is_active  = true;

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
    public function customers()
    {
        return Customer::query()
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate($this->perPage);
    }

    // Expose data to view
    public function with(): array
    {
        return [
            'customersData' => $this->customers,
            'headersData' => $this->headers,
        ];
    }

    public function openCreate(): void
    {
        $this->reset('customerId','code','name','email','phone','address','is_active');
        $this->is_active = true;
        $this->showCustomerModal = true;
    }

    public function openEdit(Customer $customer): void
    {
        $this->customerId = $customer->id;
        $this->code       = $customer->code ?? '';
        $this->name       = $customer->name ?? '';
        $this->email      = $customer->email ?? '';
        $this->phone      = $customer->phone ?? '';
        $this->address    = $customer->address ?? '';
        $this->is_active  = (bool) ($customer->is_active ?? false);
        $this->showCustomerModal = true;
    }

    public function saveCustomer(): void
    {
        $rules = [
            'code'   => 'nullable|string|max:255|unique:customers,code,'.$this->customerId,
            'name'   => 'required|string|max:255',
            'email'  => 'nullable|email|max:255|unique:customers,email,'.$this->customerId,
            'phone'  => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];

        $this->validate($rules);

        $data = [
            'code'    => $this->code ?: null,
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'address' => $this->address,
            'is_active' => $this->is_active ? 1 : 0,
        ];

        if ($this->customerId) {
            Customer::updateOrCreate(['id' => $this->customerId], $data);
            $this->success('Customer updated');
        } else {
            Customer::create($data);
            $this->success('Customer created');
        }
        $this->showCustomerModal = false;
        unset($this->customers); // Clear computed cache
    }

    public function deleteCustomer(Customer $customer): void
    {
        $customer->delete();
        $this->success('Customer deleted');
        unset($this->customers); // Clear computed cache
    }

    private function success(string $title): void
    {
        $this->toast('success', $title, '', 'toast-top toast-end', 'o-check-circle', 'alert-success', 3000);
    }
};
?>
<div>
    <x-header :title="__('Customers')" :subtitle="__('Manage customers')" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input :placeholder="__('Search customers...')" wire:model.live.debounce.300ms="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button :label="__('Create')" icon="o-plus" class="btn-primary" wire:click="openCreate" spinner="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headersData" :rows="$customersData" :sort-by="$sortBy" with-pagination 
                 per-page="perPage" :per-page-values="[5,10,20,50]" link="customers">
            @scope('cell_address', $customer)
                <div class="text-sm text-gray-700">{{ Str::limit($customer->address, 80) }}</div>
            @endscope

            @scope('cell_code', $customer)
                <span class="text-sm font-mono">{{ $customer->code ?: '-' }}</span>
            @endscope

            @scope('cell_is_active', $customer)
                @if($customer->is_active)
                    <span class="badge badge-success badge-sm">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-ghost badge-sm">{{ __('Inactive') }}</span>
                @endif
            @endscope

            @scope('actions', $customer)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="openEdit('{{ $customer->id }}')" :tooltip="__('Edit')" spinner="openEdit" />
                    <x-button icon="o-trash" wire:click="deleteCustomer('{{ $customer->id }}')" :tooltip="__('Delete')" 
                              spinner="deleteCustomer" onclick="confirm('Delete this customer?') || event.stopImmediatePropagation()" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showCustomerModal" :title="$customerId ? __('Edit Customer') : __('Create Customer')" separator>
        <x-input :label="__('Code') . ' (' . __('optional') . ')'" wire:model.defer="code" placeholder="e.g. uuid or internal code" />
        <x-input :label="__('Name')" wire:model.defer="name" />
        <x-input :label="__('Email')" wire:model.defer="email" type="email" />
        <x-input :label="__('Phone')" wire:model.defer="phone" />
        <x-textarea :label="__('Address')" wire:model.defer="address" />
        <x-toggle :label="__('Active')" wire:model.defer="is_active" />

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showCustomerModal', false)" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="saveCustomer" spinner="saveCustomer" />
        </x-slot:actions>
    </x-modal>
</div>
