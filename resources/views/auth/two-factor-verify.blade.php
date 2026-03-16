<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use PragmaRX\Google2FA\Google2FA;
use Mary\Traits\Toast;

new
#[Title('Two-Factor Verification')]
#[Layout('components.layouts.guest')]
class extends Component {
    use Toast;

    public string $verificationCode = '';

    public function verify(): void
    {
        $this->validate([
            'verificationCode' => 'required|digits:6',
        ], [
            'verificationCode.required' => 'Kode verifikasi wajib diisi',
            'verificationCode.digits' => 'Kode verifikasi harus 6 digit',
        ]);

        $user = auth()->user();
        $google2fa = new Google2FA();
        
        $valid = $google2fa->verifyKey($user->google2fa_secret, $this->verificationCode);

        if (!$valid) {
            $this->error('Kode verifikasi tidak valid. Silakan coba lagi.');
            $this->verificationCode = '';
            return;
        }

        // Mark session as verified
        session(['google2fa_verified' => true]);
        
        $this->success('Verifikasi berhasil! Mengalihkan...');
        
        // Redirect to intended page or dashboard
        return redirect()->intended(route('dashboard'));
    }

    public function logout(): void
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        
        return redirect()->route('login');
    }
}; ?>

<div class="min-h-screen flex items-center justify-center bg-base-200">
    <x-card class="w-full max-w-md shadow-xl">
        <x-slot:title>
            <div class="text-center space-y-2">
                <x-icon name="o-shield-check" class="w-16 h-16 text-primary mx-auto" />
                <h2 class="text-2xl font-bold">Two-Factor Authentication</h2>
                <p class="text-sm text-base-content/70 font-normal">Masukkan kode dari Google Authenticator</p>
            </div>
        </x-slot:title>

        <x-form wire:submit="verify">
            <x-input 
                label="Kode Verifikasi" 
                wire:model="verificationCode" 
                placeholder="000000"
                hint="Masukkan kode 6 digit dari aplikasi Google Authenticator"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                autofocus
            />

            <x-slot:actions>
                <x-button label="Logout" wire:click="logout" class="btn-ghost" icon="o-arrow-left-on-rectangle" />
                <x-button label="Verifikasi" type="submit" class="btn-primary" icon="o-check" spinner="verify" />
            </x-slot:actions>
        </x-form>

        <div class="mt-4 text-center">
            <p class="text-xs text-base-content/60">
                Tidak bisa akses aplikasi? Hubungi administrator untuk bantuan.
            </p>
        </div>
    </x-card>
</div>
