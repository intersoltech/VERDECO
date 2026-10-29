<?php

namespace App\Policies;

use App\Domain\Organization\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('plants.read');
    }

    public function view(User $user, Plant $plant): bool
    {
        return $user->canAccessPlant($plant);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('plants.manage');
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->hasPermission('plants.manage') && $user->canAccessPlant($plant);
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->hasPermission('plants.manage') && $user->canAccessPlant($plant);
    }
}
