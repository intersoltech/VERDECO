<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Costing\Models\CostPeriod;
use App\Models\User;

final class CostPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'viewer'], true);
    }

    public function view(User $user, CostPeriod $period): bool
    {
        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        return $user->plants()->where('plants.id', $period->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function update(User $user, CostPeriod $period): bool
    {
        if (in_array($period->status, ['approved', 'closed'], true)) {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function delete(User $user, CostPeriod $period): bool
    {
        // Deleting cost periods with history is prohibited
        return false;
    }

    public function approve(User $user, CostPeriod $period): bool
    {
        if ($period->status !== 'open') {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function close(User $user, CostPeriod $period): bool
    {
        if ($period->status !== 'approved') {
            return false;
        }

        // Dual control: Approver cannot close the same period
        if ($period->approved_by && $period->approved_by === $user->id) {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function reopen(User $user, CostPeriod $period): bool
    {
        if ($period->status !== 'closed') {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }
}
