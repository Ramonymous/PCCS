<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Mary\Traits\Toast;

new
#[Title('Profile Settings')]
class extends Component {
    use Toast, WithFileUploads;

    // Profile Information
    #[Validate('required|string|max:255')]
    public string $name = '';
    
    #[Validate('required|email|max:255')]
    public string $email = '';

    public $avatar;

    // Change Password
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|max:2048',
        ], [
            'name.required' => 'Nama wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'avatar.image' => 'File harus berupa gambar',
            'avatar.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        // Handle avatar upload
        if ($this->avatar) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $path = $this->avatar->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);
        
        $this->avatar = null;
        $this->success(__('Profile updated successfully.'));
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
            $this->success(__('Profile photo deleted successfully.'));
        }
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => __('Current password is required'),
            'new_password.required' => __('New password is required'),
            'new_password.confirmed' => __('Password confirmation does not match'),
            'new_password.min' => __('Password minimum 8 characters'),
        ]);

        $user = auth()->user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->error(__('Current password is incorrect.'));
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        $this->success(__('Password changed successfully.'));
    }
}; ?>

<div class="max-w-7xl mx-auto py-8 px-4">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column - Profile & Password --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Profile Information --}}
            <x-card class="shadow-lg">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <x-icon name="o-user" class="w-6 h-6 text-primary" />
                        </div>
                        <span class="text-xl font-bold">Informasi Profil</span>
                    </div>
                </x-slot:title>

                <x-form wire:submit="updateProfile" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-input 
                            label="Nama" 
                            wire:model="name" 
                            icon="o-user"
                            placeholder="Nama lengkap"
                        />

                        <x-input 
                            label="Email" 
                            wire:model="email" 
                            icon="o-envelope"
                            type="email"
                            placeholder="email@example.com"
                        />
                    </div>

                    <x-file 
                        label="Foto Profil" 
                        wire:model="avatar" 
                        accept="image/png, image/jpeg"
                        hint="Format: JPG, PNG, max 2MB"
                    >
                        @if($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" class="h-48 rounded-xl shadow-md" />
                        @elseif(auth()->user()->avatar)
                            <img src="{{ Storage::url(auth()->user()->avatar) }}" class="h-48 rounded-xl shadow-md" />
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&size=512&background=e5e7eb&color=6b7280" 
                                 class="h-48 rounded-xl shadow-md opacity-50" />
                        @endif
                    </x-file>

                    <x-slot:actions>
                        <x-button label="Simpan Perubahan" type="submit" class="btn-primary" icon="o-check" spinner="updateProfile" />
                    </x-slot:actions>
                </x-form>
            </x-card>

            {{-- Change Password --}}
            <x-card class="shadow-lg">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-warning/10 rounded-lg">
                            <x-icon name="o-lock-closed" class="w-6 h-6 text-warning" />
                        </div>
                        <span class="text-xl font-bold">Ubah Password</span>
                    </div>
                </x-slot:title>

                <x-form wire:submit="updatePassword" class="space-y-5">
                    <x-input 
                        label="Password Saat Ini" 
                        wire:model="current_password" 
                        type="password"
                        icon="o-key"
                        placeholder="••••••••"
                    />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <x-input 
                            label="Password Baru" 
                            wire:model="new_password" 
                            type="password"
                            icon="o-lock-closed"
                            placeholder="Minimal 8 karakter"
                        />

                        <x-input 
                            label="Konfirmasi Password" 
                            wire:model="new_password_confirmation" 
                            type="password"
                            icon="o-lock-closed"
                            placeholder="Ulangi password baru"
                        />
                    </div>

                    <x-slot:actions>
                        <x-button label="Ubah Password" type="submit" class="btn-warning" icon="o-shield-check" spinner="updatePassword" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- Right Column - Security & Account Info --}}
        <div class="space-y-6">
            {{-- Security Settings --}}
            <x-card class="shadow-lg">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-success/10 rounded-lg">
                            <x-icon name="o-shield-check" class="w-6 h-6 text-success" />
                        </div>
                        <span class="text-xl font-bold">Keamanan</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    {{-- Two-Factor Authentication --}}
                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-3">
                            <x-icon name="o-device-phone-mobile" class="w-6 h-6 text-primary" />
                            <div class="flex-1">
                                <h3 class="font-bold text-base">Two-Factor Authentication</h3>
                                <p class="text-xs text-base-content/60 mt-1">
                                    @if(auth()->user()->google2fa_enabled)
                                        Aktif sejak {{ auth()->user()->google2fa_enabled_at?->locale('id')->diffForHumans() }}
                                    @else
                                        Tingkatkan keamanan akun
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(auth()->user()->google2fa_enabled)
                                <x-badge value="AKTIF" class="badge-success flex-1 justify-center" />
                            @else
                                <x-badge value="NONAKTIF" class="badge-ghost flex-1 justify-center" />
                            @endif
                            <x-button 
                                label="Kelola" 
                                link="{{ route('2fa.setup') }}"
                                wire:navigate
                                class="btn-sm btn-primary" 
                                icon="o-cog-6-tooth"
                            />
                        </div>
                    </div>

                    {{-- Push Notifications --}}
                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-3">
                            <x-icon name="o-bell" class="w-6 h-6 text-info" />
                            <div>
                                <h3 class="font-bold text-base">Push Notifications</h3>
                                <p class="text-xs text-base-content/60 mt-1">Notifikasi browser</p>
                            </div>
                        </div>
                        <x-ui.push-notification-manager :show="true" />
                    </div>

                    {{-- Session Information --}}
                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3">
                            <x-icon name="o-computer-desktop" class="w-6 h-6 text-secondary" />
                            <div>
                                <h3 class="font-bold text-base">Sesi Login</h3>
                                <p class="text-xs text-base-content/60 mt-1 line-clamp-2" title="{{ request()->userAgent() }}">
                                    {{ request()->userAgent() }}
                                </p>
                                <p class="text-xs text-base-content/60 mt-1">IP: {{ request()->ip() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Account Information --}}
            <x-card class="shadow-lg">
                <x-slot:title>
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-info/10 rounded-lg">
                            <x-icon name="o-information-circle" class="w-6 h-6 text-info" />
                        </div>
                        <span class="text-xl font-bold">Info Akun</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-2">
                            <x-icon name="o-user-group" class="w-5 h-5 text-primary" />
                            <label class="font-semibold text-sm">Role</label>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            @foreach(auth()->user()->roles as $role)
                                <x-badge value="{{ $role->name }}" class="badge-primary" />
                            @endforeach
                        </div>
                    </div>

                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-2">
                            <x-icon name="o-key" class="w-5 h-5 text-secondary" />
                            <label class="font-semibold text-sm">Permissions</label>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(auth()->user()->getAllPermissions()->take(3) as $permission)
                                <x-badge value="{{ $permission->name }}" class="badge-ghost badge-sm" />
                            @endforeach
                            @if(auth()->user()->getAllPermissions()->count() > 3)
                                <x-badge value="+{{ auth()->user()->getAllPermissions()->count() - 3 }}" class="badge-ghost badge-sm" />
                            @endif
                        </div>
                    </div>

                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-2">
                            <x-icon name="o-calendar" class="w-5 h-5 text-success" />
                            <label class="font-semibold text-sm">Terdaftar</label>
                        </div>
                        <p class="text-sm">{{ auth()->user()->created_at->locale('id')->isoFormat('D MMMM YYYY') }}</p>
                        <p class="text-xs text-base-content/60 mt-1">{{ auth()->user()->created_at->locale('id')->diffForHumans() }}</p>
                    </div>

                    <div class="p-4 bg-base-200/50 rounded-xl border border-base-300">
                        <div class="flex items-center gap-3 mb-2">
                            <x-icon name="o-clock" class="w-5 h-5 text-warning" />
                            <label class="font-semibold text-sm">Terakhir Aktif</label>
                        </div>
                        <p class="text-sm">{{ auth()->user()->updated_at->locale('id')->diffForHumans() }}</p>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
