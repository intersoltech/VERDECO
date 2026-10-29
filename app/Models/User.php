<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_user', 'user_id', 'plant_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function accessiblePlantIds(): Collection
    {
        return $this->plants()->pluck('plants.id');
    }

    public function hasRole(string $role): bool
    {
        return strtolower((string) ($this->role ?? '')) === strtolower($role);
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole((string) $role)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (is_array($permissions) && in_array($permission, $permissions, true)) {
            return true;
        }

        return in_array($permission, $this->rolePermissions(), true);
    }

    public function can($ability, $arguments = []): bool
    {
        if (is_string($ability) && $this->hasPermission($ability)) {
            return true;
        }

        return parent::can($ability, $arguments);
    }

    public function canAccessPlant(Plant $plant): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->plants()->whereKey($plant->getKey())->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'plant_manager', 'plant_supervisor', 'plant_operator']);
    }

    /**
     * @return array<int, string>
     */
    protected function rolePermissions(): array
    {
        return match (strtolower((string) ($this->role ?? 'plant_operator'))) {
            'admin' => [
                'view-any-plant',
                'view-plant',
                'create-plant',
                'update-plant',
                'delete-plant',
                'view-any-alert',
                'view-alert',
                'create-alert',
                'update-alert',
                'delete-alert',
                'view-any-alert-rule',
                'view-alert-rule',
                'create-alert-rule',
                'update-alert-rule',
                'delete-alert-rule',
                'plants.read',
                'plants.manage',
                'costs.read',
                'costs.manage',
                'incidents.read',
                'incidents.manage',
                'alerts.read',
                'alerts.manage',
            ],
            'plant_manager' => [
                'view-plant',
                'view-any-plant',
                'create-alert',
                'update-alert',
                'view-alert',
                'view-any-alert',
                'view-alert-rule',
                'update-alert-rule',
                'plans.read',
                'plants.read',
                'production-lines.read',
                'production-lines.manage',
                'production-batches.read',
                'production-batches.manage',
                'costs.read',
                'costs.manage',
                'suppliers.read',
                'suppliers.manage',
                'cost-periods.read',
                'cost-periods.manage',
                'costs.read',
                'costs.manage',
                'incidents.read',
                'incidents.manage',
                'alerts.read',
                'alerts.manage',
            ],
            'plant_supervisor' => [
                'view-plant',
                'view-any-plant',
                'view-alert',
                'view-alert-rule',
                'plants.read',
                'production-lines.read',
                'production-batches.read',
                'suppliers.read',
                'cost-periods.read',
                'costs.read',
                'incidents.read',
                'alerts.read',
            ],
            'plant_operator' => [
                'view-plant',
                'plants.read',
                'production-lines.read',
                'production-batches.read',
                'suppliers.read',
                'cost-periods.read',
                'costs.read',
                'incidents.read',
                'alerts.read',
            ],
            default => [],
        };
    }
}
