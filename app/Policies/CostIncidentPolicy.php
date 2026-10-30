<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Incidents\Models\CostIncident;
use App\Models\User;

final class CostIncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'plant_operator', 'viewer'], true);
    }

    public function view(User $user, CostIncident $incident): bool
    {
        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        return $user->plants()->where('plants.id', $incident->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'plant_operator'], true);
    }

    public function update(User $user, CostIncident $incident): bool
    {
        // Closed incidents are immutable without reopening
        if ($incident->status === 'closed') {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $incident->plant_id)->exists();
        }

        // Supervisor can edit open incidents for their plant
        if ($user->role === 'plant_supervisor' && $incident->status === 'open') {
            return $user->plants()->where('plants.id', $incident->plant_id)->exists();
        }

        return false;
    }

    public function delete(User $user, CostIncident $incident): bool
    {
        if ($incident->status === 'closed') {
            return false;
        }

        return $user->role === 'admin';
    }

    public function approve(User $user, CostIncident $incident): bool
    {
        // Dual control: user who reported the incident cannot approve it
        if ($incident->reported_by_user_id && $incident->reported_by_user_id === $user->id) {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $incident->plant_id)->exists();
        }

        return false;
    }

    public function close(User $user, CostIncident $incident): bool
    {
        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $incident->plant_id)->exists();
        }

        return false;
    }

    public function reopen(User $user, CostIncident $incident): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager'], true);
    }
}
