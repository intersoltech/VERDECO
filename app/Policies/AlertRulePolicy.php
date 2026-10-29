<?php

namespace App\Policies;

use App\Domain\Alerts\Models\AlertRule;
use App\Models\User;

class AlertRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermission('alerts.read');
    }

    public function view(User $user, AlertRule $alertRule): bool
    {
        return $alertRule->plant_id !== null && $user->canAccessPlant($alertRule->plant);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('alerts.manage');
    }

    public function update(User $user, AlertRule $alertRule): bool
    {
        return $alertRule->plant_id !== null
            && $user->hasPermission('alerts.manage')
            && $user->canAccessPlant($alertRule->plant);
    }

    public function delete(User $user, AlertRule $alertRule): bool
    {
        return $alertRule->plant_id !== null
            && $user->hasPermission('alerts.manage')
            && $user->canAccessPlant($alertRule->plant);
    }
}
