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
     * Admin masuk ke kedua panel; dia juga berdiri di kasir saat ramai.
     * Kasir hanya ke panel kasir, dan akun nonaktif tidak ke mana-mana.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->active) {
            return false;
        }

        return $panel->getId() === 'admin' ? $this->isAdmin() : true;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
