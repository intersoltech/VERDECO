<?php

namespace App\Policies;

use App\Domain\Alerts\Models\AlertRule;
use App\Models\User;

class AlertRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view-any-alert-rule') || $user->can('view-alert-rule');
    }

    public function view(User $user, AlertRule $alertRule): bool
    {
        return $user->can('view-alert-rule') || $user->plants()->whereKey($alertRule->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create-alert-rule');
    }

    public function update(User $user, AlertRule $alertRule): bool
    {
        return $user->can('update-alert-rule') || $user->plants()->whereKey($alertRule->plant_id)->exists();
    }

    public function delete(User $user, AlertRule $alertRule): bool
    {
        return $user->can('delete-alert-rule');
    }
}
