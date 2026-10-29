<?php

namespace App\Policies;

use App\Domain\Organization\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any-plant') || $user->can('view-plant');
    }

    public function view(User $user, Plant $plant): bool
    {
        return $user->can('view-plant') || $user->plants()->whereKey($plant->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create-plant');
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->can('update-plant') || $user->plants()->whereKey($plant->getKey())->exists();
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->can('delete-plant');
    }
}
