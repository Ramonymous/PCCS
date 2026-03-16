<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasPushSubscriptions;

    /**
     * Tentukan guard yang digunakan untuk Spatie Permission.
     * Penting kalau kamu pakai guard "web".
     */
    protected string $guard_name = 'web';

    /**
     * Atribut yang bisa diisi secara mass-assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'google2fa_secret',
        'google2fa_enabled',
    ];

    /**
     * Atribut yang disembunyikan ketika diserialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * Cast atribut tertentu ke tipe data yang sesuai.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google2fa_enabled' => 'boolean',
            'google2fa_enabled_at' => 'datetime',
        ];
    }
}
