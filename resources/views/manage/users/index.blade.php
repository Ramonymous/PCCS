<?php
use function Livewire\Volt\{on};
use App\Models\User;
use Spatie\Permission\Models\{Role,Permission};
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;

new class extends \Livewire\Volt\Component {
    use Toast;

    /* ---------- state ---------- */
    public int  $perPage = 5;
    public bool $showUserModal = false;
    public bool $showRoleModal = false;
    public bool $showPermModal = false;

    public array $headers = [];

    // dictionaries – loaded once
    public array $roles = [];
    public array $permissions = [];

    // user form
    public ?int   $userId   = null;
    public string $name     = '';
    public string $email    = '';
    public string $password = '';
    // role/permission selections can arrive as array or single string from some select components
    public array|string  $roleIds  = [];
    public array|string  $permIds  = [];
    // quick-create inputs
    public string $newRoleName = '';
    public string $newPermName = '';

    /* ---------- lifecycle ---------- */
    public function mount(): void
    {
        $this->loadDictionaries();
        $this->headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'roles', 'label' => 'Roles'],
            ['key' => 'permissions', 'label' => 'Permissions'],
            ['key' => 'actions', 'label' => 'Actions'],
        ];
    }

    #[on('refresh')]
    public function loadDictionaries(): void
    {
        $this->roles       = Role::select('id','name')->orderBy('name')->get()->all();
        $this->permissions = Permission::select('id','name')->orderBy('name')->get()->all();
    }

    /* ---------- computed ---------- */
    #[Computed]
    public function getUsersProperty()
    {
        return User::query()
            ->select('id','name','email')
            ->with([
                'roles:id,name',
                'permissions:id,name',
            ])
            ->latest()
            ->paginate($this->perPage);
    }

    // Expose data to the view
    public function with(): array
    {
        return [
            'users' => $this->users,
        ];
    }

    /* ---------- actions ---------- */
    public function openCreate(): void
    {
        $this->reset('userId','name','email','password','roleIds','permIds');
        $this->showUserModal = true;
    }

    public function openEdit(User $user): void
    {
        $this->userId  = $user->id;
        $this->name    = $user->name;
        $this->email   = $user->email;
        $this->roleIds = $user->roles->pluck('id')->toArray();
        $this->permIds = $user->permissions->pluck('id')->toArray();
        $this->showUserModal = true;
    }

    public function saveUser(): void
    {
        // Normalize possible string payloads from selects into arrays
        $this->roleIds = array_values(array_map('intval', (array) $this->roleIds));
        $this->permIds = array_values(array_map('intval', (array) $this->permIds));

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$this->userId,
            'roleIds' => 'array',
            'roleIds.*' => 'integer|exists:roles,id',
            'permIds' => 'array',
            'permIds.*' => 'integer|exists:permissions,id',
        ];
        if (! $this->userId) $rules['password'] = 'required|string|min:6';

        $this->validate($rules);

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];
        if ($this->password) $data['password'] = Hash::make($this->password);

        $user = User::updateOrCreate(['id' => $this->userId], $data);
        $user->syncRoles($this->roleIds);       // ids – no name conversion
        $user->syncPermissions($this->permIds); // ids – no name conversion

        $this->success($this->userId ? 'User updated' : 'User created');
        $this->showUserModal = false;
        $this->password = '';
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
        $this->success('User deleted');
    }

    public function createRole(): void
    {
        $name = $this->validateOnly('newRoleName', ['newRoleName'=>'required|string|max:255|unique:roles,name'])['newRoleName'];
        $role = Role::create(['name' => $name]);
        // ensure array before appending in case the select sent a string
        $this->roleIds = array_values(array_map('intval', (array) $this->roleIds));
        $this->roleIds[] = (int) $role->id;
        $this->loadDictionaries();
        $this->success("Role '$name' created");
        $this->showRoleModal = false;
    }

    public function createPerm(): void
    {
        $name = $this->validateOnly('newPermName', ['newPermName'=>'required|string|max:255|unique:permissions,name'])['newPermName'];
        $perm = Permission::create(['name' => $name]);
        // ensure array before appending in case the select sent a string
        $this->permIds = array_values(array_map('intval', (array) $this->permIds));
        $this->permIds[] = (int) $perm->id;
        $this->loadDictionaries();
        $this->success("Permission '$name' created");
        $this->showPermModal = false;
    }

    /* ---------- helper ---------- */
    private function success(string $title): void
    {
        $this->toast('success', $title, '', 'toast-top toast-end', 'o-check-circle', 'alert-success', 3000);
    }
};
?>
<div>
    {{-- HEADER --}}
    <x-header :title="__('Users')" :subtitle="__('Manage users, roles & permissions')" separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('Create')" icon="o-plus" class="btn-primary" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    {{-- USERS TABLE --}}
    <x-card shadow>
        <x-table :headers="$headers" :rows="$users" with-pagination per-page="perPage" :per-page-values="[5,10,20]">
            @scope('cell_roles', $user)
                <div class="flex flex-wrap gap-1">
                    @forelse($user->roles as $role)
                        <span class="badge badge-primary badge-sm">{{ $role->name }}</span>
                    @empty
                        <span class="text-gray-400 text-sm">{{ __('none') }}</span>
                    @endforelse
                </div>
            @endscope

            @scope('cell_permissions', $user)
                <div class="flex flex-wrap gap-1">
                    @forelse($user->permissions as $perm)
                        <span class="badge badge-secondary badge-sm">{{ $perm->name }}</span>
                    @empty
                        <span class="text-gray-400 text-sm">{{ __('none') }}</span>
                    @endforelse
                </div>
            @endscope

            @scope('actions', $user)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" wire:click="openEdit({{ $user->id }})" :tooltip="__('Edit')" />
                    <x-button icon="o-trash" wire:click="deleteUser({{ $user->id }})" :tooltip="__('Delete')" onclick="confirm('Sure?') || event.stopImmediatePropagation()" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- CREATE / EDIT USER MODAL --}}
    <x-modal wire:model="showUserModal" :title="$userId ? __('Edit User') : __('Create User')" separator>
        <x-input :label="__('Name')" wire:model.defer="name" />
        <x-input :label="__('Email')" wire:model.defer="email" type="email" />
        <x-input :label="__('Password')" wire:model.defer="password" type="password"
                 :hint="$userId ? __('Leave blank to keep current') : __('Min 6 characters')" />

        <x-choices :label="__('Roles')" :options="$roles" option-value="id" option-label="name" wire:model="roleIds" single clearable>
            <x-slot:append>
                <x-button :label="__('New')" icon="o-plus" class="btn-primary" wire:click="$set('showRoleModal', true)" />
            </x-slot:append>
        </x-choices>

        <x-choices :label="__('Direct Permissions')" :options="$permissions" option-value="id" option-label="name" wire:model="permIds" multiple clearable>
            <x-slot:append>
                <x-button :label="__('New')" icon="o-plus" class="btn-primary" wire:click="$set('showPermModal', true)" />
            </x-slot:append>
        </x-choices>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showUserModal', false)" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="saveUser" spinner="saveUser" />
        </x-slot:actions>
    </x-modal>

    {{-- QUICK-CREATE ROLE MODAL --}}
    <x-modal wire:model="showRoleModal" :title="__('New Role')">
        <x-input :label="__('Role name')" wire:model.defer="newRoleName" placeholder="e.g. editor" />
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showRoleModal', false)" />
            <x-button :label="__('Create')" icon="o-check" class="btn-primary" wire:click="createRole" spinner="createRole" />
        </x-slot:actions>
    </x-modal>

    {{-- QUICK-CREATE PERMISSION MODAL --}}
    <x-modal wire:model="showPermModal" :title="__('New Permission')">
        <x-input :label="__('Permission name')" wire:model.defer="newPermName" placeholder="e.g. edit posts" />
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('showPermModal', false)" />
            <x-button :label="__('Create')" icon="o-check" class="btn-primary" wire:click="createPerm" spinner="createPerm" />
        </x-slot:actions>
    </x-modal>
</div>