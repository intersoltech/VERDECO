<?php

namespace App\Policies;

use App\Domain\Alerts\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any-alert') || $user->can('view-alert');
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->can('view-alert') || $user->plants()->whereKey($alert->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create-alert');
    }

    public function update(User $user, Alert $alert): bool
    {
        return $user->can('update-alert') || $user->plants()->whereKey($alert->plant_id)->exists();
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->can('delete-alert');
    }
}
