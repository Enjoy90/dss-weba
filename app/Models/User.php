<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function isManager(): bool
    {
        return $this->hasRole(UserRole::Manager);
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole(UserRole::Supervisor);
    }

    /**
     * The value of an order in rupiah is hidden from the production supervisor.
     */
    public function canSeeOrderValue(): bool
    {
        return ! $this->isSupervisor();
    }

    public function isLeader(): bool
    {
        return $this->hasRole(UserRole::Leader);
    }

    public function deactivationBlockedReason(?User $actor): ?string
    {
        if ($actor !== null && $actor->is($this)) {
            return 'Anda tidak dapat menonaktifkan akun sendiri.';
        }

        if ($this->isLastActiveAdmin()) {
            return 'Admin terakhir tidak dapat dinonaktifkan.';
        }

        return null;
    }

    public function roleChangeBlockedReason(UserRole $newRole): ?string
    {
        if ($newRole !== UserRole::Admin && $this->isLastActiveAdmin()) {
            return 'Admin terakhir tidak dapat diubah perannya. Jadikan pengguna lain Admin lebih dulu.';
        }

        return null;
    }

    public function isLastActiveAdmin(): bool
    {
        if (! $this->isAdmin() || ! $this->is_active) {
            return false;
        }

        return ! static::query()->activeAdmins()->whereKeyNot($this->getKey())->exists();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActiveAdmins(Builder $query): Builder
    {
        return $query->where('role', UserRole::Admin->value)->where('is_active', true);
    }
}
