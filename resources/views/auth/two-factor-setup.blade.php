<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Mary\Traits\Toast;

new
#[Title('Two-Factor Authentication Setup')]
#[Layout('components.layouts.app')]
class extends Component {
    use Toast;

    public string $qrCode = '';
    public string $secret = '';
    public string $verificationCode = '';
    public bool $isEnabled = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->isEnabled = $user->google2fa_enabled;

        if (!$this->isEnabled) {
            $this->generateSecret();
        }
    }

    public function generateSecret(): void
    {
        $google2fa = new Google2FA();
        $this->secret = $google2fa->generateSecretKey();
        
        $companyName = config('app.name');
        $email = auth()->user()->email;
        
        // Generate otpauth URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $companyName,
            $email,
            $this->secret
        );
        
        // Generate SVG QR Code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $this->qrCode = $writer->writeString($qrCodeUrl);
    }

    public function enable(): void
    {
        $this->validate([
            'verificationCode' => 'required|digits:6',
        ], [
            'verificationCode.required' => 'Kode verifikasi wajib diisi',
            'verificationCode.digits' => 'Kode verifikasi harus 6 digit',
        ]);

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($this->secret, $this->verificationCode);

        if (!$valid) {
            $this->error('Kode verifikasi tidak valid. Silakan coba lagi.');
            return;
        }

        $user = auth()->user();
        $user->update([
            'google2fa_secret' => $this->secret,
            'google2fa_enabled' => true,
            'google2fa_enabled_at' => now(),
        ]);

        $this->isEnabled = true;
        $this->verificationCode = '';
        $this->success('Google Authenticator berhasil diaktifkan!');
    }

    public function disable(): void
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
            return;
        }

        $user->update([
            'google2fa_secret' => null,
            'google2fa_enabled' => false,
            'google2fa_enabled_at' => null,
        ]);

        $this->isEnabled = false;
        $this->verificationCode = '';
        $this->generateSecret();
        $this->warning('Google Authenticator berhasil dinonaktifkan.');
    }
}; ?>

<div class="max-w-4xl mx-auto py-8">
    <x-card>
        <x-slot:title>
            <div class="flex items-center gap-3">
                <x-icon name="o-shield-check" class="w-6 h-6 text-primary" />
                <span>Two-Factor Authentication</span>
            </div>
        </x-slot:title>

        @if($isEnabled)
            {{-- Enabled State --}}
            <div class="space-y-6">
                <x-alert icon="o-check-circle" class="alert-success">
                    <x-slot:title>Google Authenticator Aktif</x-slot:title>
                    Akun Anda dilindungi dengan autentikasi dua faktor. Anda akan diminta memasukkan kode dari aplikasi Google Authenticator setiap kali login.
                </x-alert>

                <div class="divider">Nonaktifkan 2FA</div>

                <x-form wire:submit="disable">
                    <x-input 
                        label="Kode Verifikasi" 
                        wire:model="verificationCode" 
                        placeholder="000000"
                        hint="Masukkan kode 6 digit dari aplikasi Google Authenticator untuk menonaktifkan 2FA"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                    />

                    <x-slot:actions>
                        <x-button label="Nonaktifkan 2FA" type="submit" class="btn-error" icon="o-x-circle" spinner="disable" />
                    </x-slot:actions>
                </x-form>
            </div>
        @else
            {{-- Disabled State --}}
            <div class="space-y-6">
                <x-alert icon="o-information-circle" class="alert-warning">
                    <x-slot:title>Tingkatkan Keamanan Akun</x-slot:title>
                    Aktifkan Google Authenticator untuk menambahkan lapisan keamanan ekstra pada akun Anda.
                </x-alert>

                <div class="divider">Langkah Setup</div>

                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="badge badge-primary badge-lg">1</div>
                        <div>
                            <p class="font-semibold">Download Aplikasi</p>
                            <p class="text-sm text-base-content/70">Install Google Authenticator di smartphone Anda (tersedia di App Store & Play Store)</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="badge badge-primary badge-lg">2</div>
                        <div class="flex-1">
                            <p class="font-semibold mb-3">Scan QR Code</p>
                            @if($qrCode)
                                <div class="bg-white p-4 rounded-lg inline-block border border-base-300">
                                    {!! $qrCode !!}
                                </div>
                                <div class="mt-3">
                                    <p class="text-xs text-base-content/70 mb-2">Atau masukkan kode manual ini:</p>
                                    <div class="flex items-center gap-2">
                                        <code class="text-sm bg-base-200 px-3 py-2 rounded font-mono">{{ $secret }}</code>
                                        <x-button icon="o-clipboard" class="btn-ghost btn-sm" tooltip="Copy" 
                                                  onclick="navigator.clipboard.writeText('{{ $secret }}')" />
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="badge badge-primary badge-lg">3</div>
                        <div class="flex-1">
                            <p class="font-semibold mb-3">Verifikasi Kode</p>
                            <x-form wire:submit="enable">
                                <x-input 
                                    label="Kode Verifikasi" 
                                    wire:model="verificationCode" 
                                    placeholder="000000"
                                    hint="Masukkan kode 6 digit dari aplikasi Google Authenticator"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    inputmode="numeric"
                                />

                                <x-slot:actions>
                                    <x-button label="Aktifkan 2FA" type="submit" class="btn-primary" icon="o-shield-check" spinner="enable" />
                                </x-slot:actions>
                            </x-form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-card>
</div>
