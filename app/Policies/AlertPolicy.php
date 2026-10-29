<?php

namespace App\Policies;

use App\Domain\Alerts\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('alerts.read');
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->canAccessPlant($alert->plant);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('alerts.manage');
    }

    public function update(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.manage') && $user->canAccessPlant($alert->plant);
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->hasPermission('alerts.manage') && $user->canAccessPlant($alert->plant);
    }
}
