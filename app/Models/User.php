<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Identity\Contracts\PlantAccessibleUserInterface;
use App\Domain\Organization\Models\Plant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, PlantAccessibleUserInterface
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable {
        hasRole as protected spatieHasRole;
        hasAnyRole as protected spatieHasAnyRole;
    }

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
            'password'          => 'hashed',
            'permissions'       => 'array',
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

    public function canViewFinancials(): bool
    {
        if ($this->hasAnyRole(['admin', 'controller', 'plant_manager', 'viewer'])) {
            return true;
        }

        // Operators (plant_operator) and supervisors (plant_supervisor) cannot see financial data
        return ! $this->hasAnyRole(['plant_operator', 'plant_supervisor', 'operator', 'supervisor']);
    }

    public function hasRole($role, ?string $guard = null): bool
    {
        if (is_string($role) && strtolower((string) ($this->role ?? '')) === strtolower($role)) {
            return true;
        }

        try {
            return $this->spatieHasRole($role, $guard);
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasAnyRole(...$roles): bool
    {
        $flattened = is_array($roles[0] ?? null) ? $roles[0] : $roles;

        foreach ($flattened as $role) {
            if ($this->hasRole($role)) {
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
                'view-any-plant', 'view-plant', 'create-plant', 'update-plant', 'delete-plant',
                'view-any-alert', 'view-alert', 'create-alert', 'update-alert', 'delete-alert',
                'view-any-alert-rule', 'view-alert-rule', 'create-alert-rule', 'update-alert-rule', 'delete-alert-rule',
                'plants.read', 'plants.manage',
                'costs.read', 'costs.manage',
                'incidents.read', 'incidents.manage',
                'alerts.read', 'alerts.manage',
                'materials.read', 'materials.manage',
                'product-grades.read', 'product-grades.manage',
                'standard-costs.read', 'standard-costs.manage',
                'units.read', 'units.manage',
                'cost-categories.read', 'cost-categories.manage',
                'budgets.read', 'budgets.manage',
                'suppliers.read', 'suppliers.manage',
                'production-lines.read', 'production-lines.manage',
                'production-batches.read', 'production-batches.manage',
                'cost-periods.read', 'cost-periods.manage',
            ],
            'plant_manager' => [
                'view-plant', 'view-any-plant',
                'create-alert', 'update-alert', 'view-alert', 'view-any-alert',
                'view-alert-rule', 'update-alert-rule',
                'plants.read',
                'production-lines.read', 'production-lines.manage',
                'production-batches.read', 'production-batches.manage',
                'costs.read', 'costs.manage',
                'suppliers.read', 'suppliers.manage',
                'cost-periods.read', 'cost-periods.manage',
                'incidents.read', 'incidents.manage',
                'alerts.read', 'alerts.manage',
                'materials.read', 'materials.manage',
                'product-grades.read', 'product-grades.manage',
                'standard-costs.read', 'standard-costs.manage',
                'units.read',
                'cost-categories.read', 'cost-categories.manage',
                'budgets.read', 'budgets.manage',
            ],
            'plant_supervisor' => [
                'view-plant', 'view-any-plant',
                'view-alert', 'view-alert-rule',
                'plants.read',
                'production-lines.read',
                'production-batches.read',
                'suppliers.read',
                'materials.read',
                'product-grades.read',
                'standard-costs.read',
                'units.read',
                'cost-categories.read',
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
                'materials.read',
                'product-grades.read',
                'units.read',
                'cost-periods.read',
                'costs.read',
                'incidents.read',
                'alerts.read',
            ],
            default => [],
        };
    }
}
