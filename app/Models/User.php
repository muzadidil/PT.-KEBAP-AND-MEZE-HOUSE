<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'locale',
        'active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
        ];
    }

    /**
     * Tiap peran hanya ke panelnya sendiri: Super Admin dan Admin ke panel
     * admin, kasir ke panel kasir. Penjualan dipegang kasir saja, jadi admin
     * tidak lagi ikut membuka halaman kasir. Akun nonaktif tidak ke mana-mana.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && $panel->getId() === $this->role?->panel();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Halaman pertama akun ini: dasbor admin, atau halaman kasir. */
    public function homeUrl(): string
    {
        return $this->role?->panel() === 'admin'
            ? route('filament.admin.pages.dashboard')
            : route('filament.cashier.pages.register');
    }
}
